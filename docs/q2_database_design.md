# Q2 — Database Design: Trucks & Location History

## Schema

```sql
-- ─────────────────────────────────────────────────────────────────────────────
-- trucks
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE trucks (
    truck_id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    license_plate_number VARCHAR(20)  NOT NULL,
    created_at           DATETIME     NULL,
    updated_at           DATETIME     NULL,

    -- Unique index: the most frequent access pattern is plate→truck_id lookup.
    -- A UNIQUE index doubles as the lookup index so no extra index is needed.
    UNIQUE KEY idx_trucks_plate (license_plate_number)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────────────────
-- location_history
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE location_history (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    truck_id   INT UNSIGNED    NOT NULL,
    timestamp  DATETIME        NOT NULL,
    latitude   DECIMAL(10, 7)  NOT NULL,   -- range [-90,   90], ~1 cm precision
    longitude  DECIMAL(11, 7)  NOT NULL,   -- range [-180, 180], ~1 cm precision
    address    VARCHAR(500)    NULL,

    -- Composite index on (truck_id, timestamp):
    --   • Enables efficient "latest location for one truck"
    --     (... WHERE truck_id = ? ORDER BY timestamp DESC LIMIT 1)
    --   • Also covers time-range history queries for a single truck
    KEY idx_lh_truck_ts (truck_id, timestamp),

    CONSTRAINT fk_lh_truck
        FOREIGN KEY (truck_id) REFERENCES trucks (truck_id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────────────────
-- truck_current_location  (denormalised "hot" table)
-- ─────────────────────────────────────────────────────────────────────────────
-- One row per truck; upserted on every GPS ping.
-- Keeps the radius-search query off the million-row location_history table.
CREATE TABLE truck_current_location (
    truck_id   INT UNSIGNED    PRIMARY KEY,
    timestamp  DATETIME        NOT NULL,
    latitude   DECIMAL(10, 7)  NOT NULL,
    longitude  DECIMAL(11, 7)  NOT NULL,

    -- B-tree index on (latitude, longitude):
    --   The bounding-box WHERE clause can use a range scan on the leading
    --   column (latitude) to eliminate most rows before checking longitude.
    KEY idx_current_loc_lat_lng (latitude, longitude),

    CONSTRAINT fk_current_loc_truck
        FOREIGN KEY (truck_id) REFERENCES trucks (truck_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;
```

---

## Radius-search query

```sql
-- Find all trucks whose latest known location is within :radius_km of (:lat, :lng).

SET @lat       = -6.200000;
SET @lng       = 106.816666;
SET @radius_km = 10.0;

-- Bounding-box deltas (rough approximation):
--   1° of latitude  ≈ 111 km everywhere
--   1° of longitude ≈ 111 km × cos(lat) at the given latitude
SET @lat_delta = @radius_km / 111.0;
SET @lng_delta = @radius_km / (111.0 * COS(RADIANS(@lat)));

SELECT
    t.truck_id,
    t.license_plate_number,
    tcl.latitude,
    tcl.longitude,
    tcl.timestamp  AS last_seen_at,
    -- Haversine distance (km) — accurate enough for practical use
    (6371 * ACOS(
        LEAST(1.0,
            COS(RADIANS(@lat)) * COS(RADIANS(tcl.latitude))
            * COS(RADIANS(tcl.longitude) - RADIANS(@lng))
            + SIN(RADIANS(@lat)) * SIN(RADIANS(tcl.latitude))
        )
    )) AS distance_km
FROM trucks               t
JOIN truck_current_location tcl ON tcl.truck_id = t.truck_id
WHERE
    -- Step 1 — bounding box: uses the (latitude, longitude) B-tree index
    tcl.latitude  BETWEEN @lat - @lat_delta AND @lat + @lat_delta
    AND tcl.longitude BETWEEN @lng - @lng_delta AND @lng + @lng_delta
HAVING
    -- Step 2 — precise Haversine filter applied only to the small bounding-box result
    distance_km <= @radius_km
ORDER BY distance_km;
```

---

## Design choices & trade-offs

### 1. `DECIMAL(10,7)` / `DECIMAL(11,7)` for coordinates
Seven decimal places give ~1 cm precision (more than GPS receivers provide).
`FLOAT` would save 2 bytes per row but introduces floating-point rounding that
can cause spurious inequality comparisons on range queries. `DECIMAL` is exact.

### 2. `DATETIME` instead of `TIMESTAMP`
`TIMESTAMP` is stored as UTC seconds and has a hard upper bound of 2038-01-19
(Year-2038 problem). `DATETIME` stores the literal value without timezone
conversion and has a range up to 9999. Applications handle timezone in code.

### 3. `VARCHAR(20)` + `UNIQUE` for `license_plate_number`
Indonesian plates fit comfortably in 15 characters; 20 gives safe headroom.
`utf8mb4_unicode_ci` collation makes the lookup case-insensitive
(`'b 1234 abc' = 'B 1234 ABC'`), which matches real-world data inconsistencies.
The UNIQUE constraint implicitly creates the lookup index.

### 4. Composite index `(truck_id, timestamp)` on `location_history`
This is the most important index on the large table. It satisfies:
- Latest-location lookup: `WHERE truck_id = ? ORDER BY timestamp DESC LIMIT 1`
- History range query: `WHERE truck_id = ? AND timestamp BETWEEN ? AND ?`
A reverse scan (DESC) on the index directly returns the newest row without a
filesort, which is critical for tens of millions of rows.

### 5. Denormalised `truck_current_location` table
**The core design decision.** The alternative — a correlated subquery or
`GROUP BY truck_id` with `MAX(timestamp)` — forces MySQL to scan (or at best
range-scan) the entire `location_history` table to produce the "latest row per
truck" result set, and _then_ apply the spatial filter. With tens of millions of
rows this is expensive even with indexes.

By materialising the current location into a separate ~tens-of-thousands-row
table, the radius search only touches that small table. The bounding-box
`WHERE` can use the `(latitude, longitude)` B-tree index for a fast range scan,
narrowing candidates to a small set before Haversine is evaluated in `HAVING`.

**Write overhead:** Every GPS ping must upsert both `location_history` and
`truck_current_location` (ideally inside a transaction or a DB trigger). This
is a constant-time O(1) extra write per ping — acceptable given the read
performance gain.

### 6. Two-pass spatial filter (bounding box + Haversine)
The bounding box `WHERE` is cheap (B-tree range scan). Haversine in `HAVING`
is computed only for the small result set that passes the box filter.
A pure Haversine `WHERE` without the bounding box cannot use any B-tree index
and degrades to a full-table scan.

### 7. `LEAST(1.0, ...)` guard in the Haversine formula
`ACOS` is undefined for values slightly above 1.0, which can occur due to
floating-point rounding when the two points are identical. `LEAST` clamps to
a safe value.

### Alternative considered: MySQL Spatial (POINT + SPATIAL INDEX)
MySQL 5.7+ supports `ST_Distance_Sphere` and `POINT` columns with spatial
indexes (R-tree). This would be more expressive and handle edge cases like
the antimeridian. However:
- R-tree indexes have higher maintenance overhead per insert.
- `ST_Distance_Sphere` is not available in MariaDB < 10.5.
- The bounding-box + Haversine approach is often _faster_ for radius queries
  because a B-tree range scan on a narrow lat/lng window is very efficient.

For a production system that needs sub-metre accuracy or supports large radii
(hundreds of km), switching to spatial would be the right call.
