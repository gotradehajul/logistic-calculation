@extends('layouts.app')
@section('title', 'Q2 — Database Design')

@section('content')

<div class="mb-8">
    <div class="text-xs font-bold text-purple-500 uppercase tracking-wider mb-1">Question 2</div>
    <h1 class="text-3xl font-extrabold text-gray-900 mb-2">Database Design</h1>
    <p class="text-gray-500 max-w-2xl">
        MySQL/MariaDB schema for finding trucks within a radius, with indexing strategy,
        an optimised radius-search query, and design rationale.
    </p>
</div>

{{-- Tab nav --}}
<div id="tabs" class="flex gap-2 mb-6">
    @foreach(['schema' => 'Schema', 'query' => 'Radius Query', 'design' => 'Design Notes'] as $id => $label)
        <button onclick="showTab('{{ $id }}')" id="tab-{{ $id }}"
                class="tab-btn text-sm font-medium px-4 py-2 rounded-lg border transition-colors">
            {{ $label }}
        </button>
    @endforeach
</div>

{{-- Schema tab --}}
<div id="pane-schema" class="tab-pane">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- trucks --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 bg-purple-50 border-b border-purple-100 flex items-center justify-between">
                <span class="font-bold text-purple-800 text-sm">trucks</span>
                <span class="text-xs text-purple-500">~tens of thousands rows</span>
            </div>
            <div class="p-5">
                <pre class="!m-0 !text-xs"><code class="language-sql">CREATE TABLE trucks (
  truck_id INT UNSIGNED
    AUTO_INCREMENT PRIMARY KEY,

  -- Fast lookup by plate number.
  -- UNIQUE doubles as the index.
  license_plate_number
    VARCHAR(20) NOT NULL,

  created_at DATETIME NULL,
  updated_at DATETIME NULL,

  UNIQUE KEY idx_trucks_plate
    (license_plate_number)
) ENGINE=InnoDB
  CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;</code></pre>
            </div>
        </div>

        {{-- location_history --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 bg-purple-50 border-b border-purple-100 flex items-center justify-between">
                <span class="font-bold text-purple-800 text-sm">location_history</span>
                <span class="text-xs text-purple-500">tens of millions rows</span>
            </div>
            <div class="p-5">
                <pre class="!m-0 !text-xs"><code class="language-sql">CREATE TABLE location_history (
  id        BIGINT UNSIGNED
              AUTO_INCREMENT PRIMARY KEY,
  truck_id  INT UNSIGNED NOT NULL,

  -- DATETIME avoids Year-2038 problem
  -- of TIMESTAMP; store UTC in app.
  timestamp DATETIME NOT NULL,

  -- DECIMAL(x,7) ≈ 1 cm precision
  latitude  DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(11,7) NOT NULL,

  address   VARCHAR(500) NULL,

  -- Key index: covers latest-location
  -- lookup AND history range queries.
  KEY idx_lh_truck_ts
    (truck_id, timestamp),

  FOREIGN KEY (truck_id)
    REFERENCES trucks(truck_id)
    ON DELETE CASCADE
) ENGINE=InnoDB CHARSET=utf8mb4;</code></pre>
            </div>
        </div>

        {{-- truck_current_location --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 bg-purple-50 border-b border-purple-100 flex items-center justify-between">
                <span class="font-bold text-purple-800 text-sm">truck_current_location</span>
                <span class="text-xs text-purple-500">one row per truck</span>
            </div>
            <div class="p-5">
                <pre class="!m-0 !text-xs"><code class="language-sql">-- Denormalised "hot" table.
-- Upserted on every GPS ping so the
-- radius search never touches the
-- million-row history table.
CREATE TABLE truck_current_location (
  truck_id  INT UNSIGNED PRIMARY KEY,
  timestamp DATETIME NOT NULL,
  latitude  DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(11,7) NOT NULL,

  -- B-tree range scan on leading
  -- column (lat) narrows candidates
  -- before Haversine is evaluated.
  KEY idx_current_loc_lat_lng
    (latitude, longitude),

  FOREIGN KEY (truck_id)
    REFERENCES trucks(truck_id)
    ON DELETE CASCADE
) ENGINE=InnoDB;</code></pre>
            </div>
        </div>

    </div>

    {{-- Migration note --}}
    <div class="mt-4 flex items-start gap-3 bg-green-50 border border-green-200 rounded-xl p-4 text-sm">
        <span class="text-green-500 text-lg mt-0.5">✓</span>
        <div>
            <strong class="text-green-800">Migrations applied</strong>
            <span class="text-green-700 ml-1">— all three tables exist in the <code class="bg-green-100 px-1 rounded">logisly</code> MySQL database.</span>
        </div>
    </div>
</div>

{{-- Radius Query tab --}}
<div id="pane-query" class="tab-pane hidden">
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">Find trucks within N km of a point (two-pass)</h3>
            <p class="text-sm text-gray-500 mt-1">
                Step 1 — bounding box: uses the <code class="bg-gray-100 px-1 rounded">(latitude, longitude)</code> B-tree index to eliminate most rows cheaply.<br>
                Step 2 — Haversine in <code class="bg-gray-100 px-1 rounded">HAVING</code>: precise distance applied only to the small box-filtered result set.
            </p>
        </div>
        <div class="p-6">
            <pre class="!m-0"><code class="language-sql">SET @lat       = -6.200000;   -- center point latitude
SET @lng       = 106.816666;  -- center point longitude
SET @radius_km = 10.0;        -- search radius in km

-- Bounding-box deltas (approximate, good enough for pre-filter)
-- 1° latitude  ≈ 111 km everywhere
-- 1° longitude ≈ 111 km × cos(lat)
SET @lat_delta = @radius_km / 111.0;
SET @lng_delta = @radius_km / (111.0 * COS(RADIANS(@lat)));

SELECT
    t.truck_id,
    t.license_plate_number,
    tcl.latitude,
    tcl.longitude,
    tcl.timestamp  AS last_seen_at,

    -- Haversine formula — accurate enough for practical logistics use
    (6371 * ACOS(
        LEAST(1.0,                          -- guard against float rounding > 1
            COS(RADIANS(@lat))
            * COS(RADIANS(tcl.latitude))
            * COS(RADIANS(tcl.longitude) - RADIANS(@lng))
            + SIN(RADIANS(@lat))
            * SIN(RADIANS(tcl.latitude))
        )
    )) AS distance_km

FROM trucks t
JOIN truck_current_location tcl
  ON tcl.truck_id = t.truck_id

WHERE
    -- Step 1: bounding-box filter — uses idx_current_loc_lat_lng B-tree index
    tcl.latitude  BETWEEN @lat - @lat_delta AND @lat + @lat_delta
AND tcl.longitude BETWEEN @lng - @lng_delta AND @lng + @lng_delta

HAVING
    -- Step 2: precise Haversine filter on the already-small candidate set
    distance_km <= @radius_km

ORDER BY distance_km;</code></pre>
        </div>
    </div>
</div>

{{-- Design Notes tab --}}
<div id="pane-design" class="tab-pane hidden">
    <div class="space-y-4">

        @foreach([
            [
                'icon' => '🔑',
                'color' => 'blue',
                'title' => 'Denormalised truck_current_location table',
                'body' => 'The core design decision. Without it, every radius search must compute MAX(timestamp) per truck over tens of millions of rows — expensive even with indexes. By materialising the current location in a separate small table (one row per truck), the radius query only scans ≤ number-of-trucks rows and can use a fast B-tree range scan on (latitude, longitude). Write overhead is constant: one extra upsert per GPS ping inside a transaction.',
            ],
            [
                'icon' => '📐',
                'color' => 'purple',
                'title' => 'Two-pass spatial filter: bounding box + Haversine',
                'body' => 'A pure Haversine WHERE clause cannot use any B-tree index and degrades to a full-table scan. The bounding-box WHERE (BETWEEN on lat and lng) narrows the result to a small geographic window using the B-tree index. Haversine is then applied in HAVING only to that small result set — precise and fast.',
            ],
            [
                'icon' => '📏',
                'color' => 'emerald',
                'title' => 'DECIMAL(10,7) for coordinates',
                'body' => 'Seven decimal places give ~1 cm precision, more than any GPS receiver provides. FLOAT saves 2 bytes per row but introduces floating-point rounding that can produce spurious inequality mismatches in range queries. DECIMAL is exact and the storage cost is negligible.',
            ],
            [
                'icon' => '⏰',
                'color' => 'orange',
                'title' => 'DATETIME over TIMESTAMP',
                'body' => 'TIMESTAMP has a hard upper bound of 2038-01-19 (Year-2038 problem) and silently converts values with the server timezone. DATETIME stores the literal value without timezone conversion and supports dates up to 9999. Applications handle UTC conversion at the application layer.',
            ],
            [
                'icon' => '🔤',
                'color' => 'pink',
                'title' => 'utf8mb4_unicode_ci for license plates',
                'body' => 'Case-insensitive collation means "B 1234 ABC" and "b 1234 abc" match — important since GPS devices and operators use inconsistent casing. The UNIQUE index on license_plate_number doubles as the fast lookup index for plate-to-truck_id queries, so no separate index is needed.',
            ],
            [
                'icon' => '🗂️',
                'color' => 'slate',
                'title' => 'Composite index (truck_id, timestamp) on location_history',
                'body' => 'This index satisfies: (1) latest-location lookup per truck (WHERE truck_id = ? ORDER BY timestamp DESC LIMIT 1) — a reverse index scan returns the newest row without filesort; (2) time-range history queries (WHERE truck_id = ? AND timestamp BETWEEN ? AND ?). The leading column must be truck_id for point-lookup selectivity.',
            ],
        ] as $card)
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 flex gap-4">
                <div class="text-2xl mt-0.5">{{ $card['icon'] }}</div>
                <div>
                    <h4 class="font-bold text-gray-900 mb-1 text-sm">{{ $card['title'] }}</h4>
                    <p class="text-sm text-gray-600 leading-relaxed">{{ $card['body'] }}</p>
                </div>
            </div>
        @endforeach

    </div>
</div>

@endsection

@push('scripts')
<script>
function showTab(id) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.remove('bg-purple-600', 'text-white', 'border-purple-600');
        b.classList.add('border-gray-200', 'text-gray-600', 'hover:border-purple-300', 'hover:text-purple-600');
    });
    document.getElementById('pane-' + id).classList.remove('hidden');
    const btn = document.getElementById('tab-' + id);
    btn.classList.add('bg-purple-600', 'text-white', 'border-purple-600');
    btn.classList.remove('border-gray-200', 'text-gray-600');
}
document.addEventListener('DOMContentLoaded', () => showTab('schema'));
</script>
@endpush
