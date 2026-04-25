# Logistic Calculation — Backend Engineer Take-Home Quiz

**Live demo:** [https://logisly.hellominhaj.com](https://logisly.hellominhaj.com)

Built with **Laravel 13** · **PHP 8.3** · **MySQL 9**

---

## Overview

This repository contains solutions to a three-part backend engineering take-home quiz for a logistics platform. Each question is implemented as a working Laravel feature with tests, and is accessible through an interactive web UI.

| | Question | Route | Source |
|---|---|---|---|
| Q1 | WhatsApp Message Parser | `/q1` | `app/Services/WhatsApp/` |
| Q2 | Geospatial Database Design | `/q2` | `database/migrations/` |
| Q3 | Term of Payment Calculator | `/q3` | `app/Services/TopCalculator.php` |

---

## Q1 — WhatsApp Message Parser

### Problem

Transform raw WhatsApp logistics messages into structured JSON. Messages are typed on a phone, so they contain inconsistent casing, WhatsApp bold/italic markers (`*`, `_`), optional fields, and date formats with many variations.

**Input example:**
```
*Dear Team Transporter*
*Remind Order*
*Planning Loading*
*Rabu, 23 Oktober 2024*

*Origin KCS Karawang*
Csa Cikupa + Rajeg 45 Cbm 1 Unit (Gudang Bayur)
Tuj Pekalongan 46 Cbm *2 Unit*
Csa Rajeg 47 Cbm 1 Unit

_*Pastikan Driver memakai (Sepatu Safety, Berpkaian rapi, tanda pengenal,Helm & Safety Vest)*_
*Terima kasih*
```

**Output:**
```json
{
    "date": "2024-10-23",
    "origin": "KCS Karawang",
    "items": [
        {
            "destinations": ["Csa Cikupa", "Rajeg"],
            "volumeCbm": 45,
            "unitCount": 1,
            "poDate": null,
            "notes": "(Gudang Bayur)"
        },
        {
            "destinations": ["Tuj Pekalongan"],
            "volumeCbm": 46,
            "unitCount": 2,
            "poDate": null,
            "notes": null
        },
        {
            "destinations": ["Csa Rajeg"],
            "volumeCbm": 47,
            "unitCount": 1,
            "poDate": null,
            "notes": null
        }
    ],
    "safetyNote": "Pastikan Driver memakai (Sepatu Safety, Berpkaian rapi, tanda pengenal,Helm & Safety Vest)"
}
```

### Approach

The parser is split into two classes:

**`DateParser`** (`app/Services/WhatsApp/DateParser.php`)  
Handles every date format variation from the spec:
- Full Indonesian month names (`Oktober`), abbreviated (`Okt`), or numeric (`10`)
- Optional day-of-week prefix (`Rabu,`) with or without a comma
- Day-of-month with or without a leading zero
- 2-digit or 4-digit years (`24` → `2024`)

**`WhatsAppMessageParser`** (`app/Services/WhatsApp/WhatsAppMessageParser.php`)  
Processes each line through a simple state machine:
1. Strip all `*` and `_` formatting markers
2. Skip known header lines (`Dear Team Transporter`, `Remind Order`, `Order Baru`, `Planning Loading`)
3. Match the date line via `DateParser`
4. Match the `Origin …` line
5. Parse all remaining lines as cargo items until the safety note or footer
6. Cargo line parsing uses a non-greedy regex anchor on `<N> Cbm <N> Unit` — everything before it is destinations (split by `+`), everything after is optionally a parenthetical note or a `PO [Tgl] <date>` token

### Key handling

| Challenge | Solution |
|---|---|
| `*Csa Cengkareng 47 Cbm *4 Unit* *Urgent*` | Strip all `*` first → `Csa Cengkareng 47 Cbm 4 Unit Urgent`; trailing text after unit count is ignored |
| `TSM Purwakarta+Dlj Karawang` | Split destinations by `+` and trim; works with or without spaces around `+` |
| `*PO Tgl 28 Okt 24*` | Extracted before the core cargo regex runs; `DateParser` handles the abbreviated 2-digit year |
| Double spaces (`Sample  Koneksi Benoa`) | Whitespace normalised with `preg_replace('/\s+/', ' ', ...)` |

### Source files

```
app/Services/WhatsApp/
├── DateParser.php               # Indonesian date string → YYYY-MM-DD
└── WhatsAppMessageParser.php    # Full message → structured array

app/Http/Controllers/
└── WhatsAppParserController.php # POST /api/whatsapp/parse (JSON API)

tests/Unit/
└── WhatsAppParserTest.php       # 8 unit tests covering all 4 example messages
```

---

## Q2 — Database Design

### Problem

Design MySQL/MariaDB tables to find trucks within a given radius of a point, based on their **latest known location**. The `location_history` table will contain millions to tens of millions of rows.

### Schema

#### `trucks` — tens of thousands of rows · `ENGINE=InnoDB` · `utf8mb4_unicode_ci`

| Column | Type | Null | Key | Notes |
|---|---|---|---|---|
| `truck_id` | `INT UNSIGNED` | NO | PRIMARY | Auto-increment |
| `license_plate_number` | `VARCHAR(20)` | NO | UNIQUE `idx_trucks_plate` | Case-insensitive lookup; UNIQUE doubles as the query index |
| `created_at` | `DATETIME` | YES | — | |
| `updated_at` | `DATETIME` | YES | — | |

---

#### `location_history` — millions to tens of millions of rows · `ENGINE=InnoDB` · `utf8mb4_unicode_ci`

| Column | Type | Null | Key | Notes |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | PRIMARY | Auto-increment |
| `truck_id` | `INT UNSIGNED` | NO | INDEX (leading) | FK → `trucks.truck_id` |
| `timestamp` | `DATETIME` | NO | INDEX (trailing) | `DATETIME` avoids the Year-2038 problem of `TIMESTAMP` |
| `latitude` | `DECIMAL(10,7)` | NO | — | Range `[-90, 90]`, ~1 cm precision |
| `longitude` | `DECIMAL(11,7)` | NO | — | Range `[-180, 180]`, ~1 cm precision |
| `address` | `VARCHAR(500)` | YES | — | Reverse-geocoded human-readable label |

**Indexes**

| Name | Columns | Purpose |
|---|---|---|
| `PRIMARY` | `id` | Row identity |
| `idx_lh_truck_ts` | `(truck_id, timestamp)` | Latest-location lookup per truck (`ORDER BY timestamp DESC LIMIT 1`) and history range queries |

---

#### `truck_current_location` — one row per truck · `ENGINE=InnoDB`

> **Why this table exists:** finding the latest row per truck from `location_history` (tens of millions of rows) requires a `MAX(timestamp)` subquery or `GROUP BY` — both scan a large fraction of the table. By materialising the current location here and upserting on every GPS ping, the radius search only touches this small table.

| Column | Type | Null | Key | Notes |
|---|---|---|---|---|
| `truck_id` | `INT UNSIGNED` | NO | PRIMARY | FK → `trucks.truck_id`; one row per truck |
| `timestamp` | `DATETIME` | NO | — | Time of the most recent ping |
| `latitude` | `DECIMAL(10,7)` | NO | INDEX (leading) | Used by bounding-box filter |
| `longitude` | `DECIMAL(11,7)` | NO | INDEX (trailing) | Used by bounding-box filter |

**Indexes**

| Name | Columns | Purpose |
|---|---|---|
| `PRIMARY` | `truck_id` | One-row-per-truck identity |
| `idx_current_loc_lat_lng` | `(latitude, longitude)` | B-tree range scan for bounding-box `WHERE` clause |

### Radius query (two-pass)

```sql
SET @lat = -6.200000; SET @lng = 106.816666; SET @radius_km = 10.0;
SET @lat_delta = @radius_km / 111.0;
SET @lng_delta = @radius_km / (111.0 * COS(RADIANS(@lat)));

SELECT t.truck_id, t.license_plate_number,
       tcl.latitude, tcl.longitude, tcl.timestamp AS last_seen_at,
       (6371 * ACOS(LEAST(1.0,
           COS(RADIANS(@lat)) * COS(RADIANS(tcl.latitude))
           * COS(RADIANS(tcl.longitude) - RADIANS(@lng))
           + SIN(RADIANS(@lat)) * SIN(RADIANS(tcl.latitude))
       ))) AS distance_km
FROM trucks t
JOIN truck_current_location tcl ON tcl.truck_id = t.truck_id
WHERE tcl.latitude  BETWEEN @lat - @lat_delta AND @lat + @lat_delta
  AND tcl.longitude BETWEEN @lng - @lng_delta AND @lng + @lng_delta
HAVING distance_km <= @radius_km
ORDER BY distance_km;
```

### Design decisions

**`truck_current_location` denormalised table** — the core decision. Without it, every radius search must compute `MAX(timestamp)` per truck across tens of millions of rows. By materialising the current location (one row per truck, upserted on every GPS ping), the radius query only scans ≤ number-of-trucks rows and uses a fast B-tree range scan.

**Two-pass spatial filter** — the bounding-box `WHERE` uses the `(latitude, longitude)` B-tree index to narrow candidates cheaply. Haversine is then applied in `HAVING` only to that small result set. A pure Haversine `WHERE` clause cannot use any B-tree index and causes a full-table scan.

**`DECIMAL(10,7)` for coordinates** — 7 decimal places give ~1 cm precision. `FLOAT` saves 2 bytes per row but introduces rounding that can break range comparisons.

**`DATETIME` over `TIMESTAMP`** — avoids the Year-2038 problem and silent timezone conversion.

**`utf8mb4_unicode_ci` for license plates** — case-insensitive matching (`'B 1234 ABC' = 'b 1234 abc'`) without an extra `LOWER()` call; the `UNIQUE` index doubles as the lookup index.

### Source files

```
database/migrations/
├── 2024_10_23_000001_create_trucks_table.php
├── 2024_10_23_000002_create_location_history_table.php
└── 2024_10_23_000003_create_truck_current_location_table.php

app/Models/
├── Truck.php
├── LocationHistory.php
└── TruckCurrentLocation.php

docs/
└── q2_database_design.md    # Full schema DDL, query, and extended design notes
```

---

## Q3 — Term of Payment (TOP) Calculator

### Problem

Calculate the final Term of Payment for transporter invoices, applying individual caps per document type and a global ceiling.

### Formula

```
podDelay  = min( max(podLateDays,  0), 30 )
epodDelay = min( max(epodLateDays, 0), 30 )
penalty   = podDelay + epodDelay
total     = baselineTop + penalty
result    = min(total, 45)
```

Constants: `MAX_DELAY_POD = 30`, `MAX_DELAY_EPOD = 30`, `MAX_TOP = 45`

### Implementation

```php
// app/Services/TopCalculator.php
public function calculate(int $baselineTop, int $podLateDays, int $epodLateDays): int
{
    $podDelay  = min(max($podLateDays,  0), self::MAX_DELAY_POD);
    $epodDelay = min(max($epodLateDays, 0), self::MAX_DELAY_EPOD);

    return min($baselineTop + $podDelay + $epodDelay, self::MAX_TOP);
}
```

### Test cases (all 8 passing)

| # | Baseline | POD Late | ePOD Late | Expected | Result |
|---|---|---|---|---|---|
| 1 | 7 | 5 | 3 | **15** | ✓ |
| 2 | 10 | 35 | 25 | **45** | ✓ POD capped at 30, total → 45 |
| 3 | 20 | 30 | 30 | **45** | ✓ Both at max, total → 45 |
| 4 | 14 | 0 | 0 | **14** | ✓ No penalty |
| 5 | 5 | -2 | 0 | **5** | ✓ Negative treated as 0 |
| 6 | 15 | 20 | 15 | **45** | ✓ Total 50 → capped |
| 7 | 10 | 30 | 0 | **40** | ✓ POD at max, ePOD zero |
| 8 | 45 | 0 | 0 | **45** | ✓ Baseline at ceiling |

### Additional questions

**a) Why cap POD and ePOD individually rather than the total penalty?**  
Individual caps enforce a per-document-type fairness rule: neither document alone can impose more than 30 days of penalty. If only the total were capped, one extremely late document would consume the entire penalty budget and hide the other document's lateness status.

**b) What if the final 45-day cap were removed?**  
The theoretical maximum would be `baseline + 60`. A transporter with a 30-day baseline and both documents 45 days late would wait 90 days — triggering cash-flow crises, inability to cover fuel and driver salaries, and potential supply-chain disruption. The cap is a business viability safeguard.

**c) How to make caps configurable?**  
Two options: (1) `config/top.php` reading from `.env` — zero DB overhead, per-environment values, requires a config cache clear after a change; (2) a `top_config` DB table with a short-lived cache — allows runtime edits via a back-office UI without any deployment. Start with config files; promote to DB when a live-editing requirement emerges.

### Source files

```
app/Services/
└── TopCalculator.php                    # Core calculation logic

app/Http/Controllers/
└── TopCalculatorController.php          # POST /api/top/calculate (JSON API)

tests/Unit/
└── TopCalculatorTest.php                # 10 unit tests (8 spec cases + 2 extra edge cases)

docs/
└── q3_top_additional_answers.md         # Extended written answers
```

---

## Running locally

```bash
git clone <repo>
cd logistic-calculation

composer install

cp .env.example .env
# Set DB_CONNECTION=mysql and your DB credentials in .env

php artisan key:generate
php artisan migrate

php artisan serve
```

Open [http://localhost:8000](http://localhost:8000).

## Running tests

```bash
php artisan test
```

```
PASS  Tests\Unit\TopCalculatorTest    (10 tests)
PASS  Tests\Unit\WhatsAppParserTest   (8 tests)

Tests: 18 passed (37 assertions)
```

## Project structure

```
app/
├── Http/Controllers/
│   ├── QuizController.php              # Web UI controller (index, q1, q2, q3)
│   ├── WhatsAppParserController.php    # API: POST /api/whatsapp/parse
│   └── TopCalculatorController.php     # API: POST /api/top/calculate
├── Models/
│   ├── Truck.php
│   ├── LocationHistory.php
│   └── TruckCurrentLocation.php
└── Services/
    ├── TopCalculator.php
    └── WhatsApp/
        ├── DateParser.php
        └── WhatsAppMessageParser.php

database/migrations/
├── 2024_10_23_000001_create_trucks_table.php
├── 2024_10_23_000002_create_location_history_table.php
└── 2024_10_23_000003_create_truck_current_location_table.php

resources/views/
├── layouts/app.blade.php
├── index.blade.php
├── q1.blade.php
├── q2.blade.php
└── q3.blade.php

routes/
├── web.php     # GET / | GET+POST /q1 | GET /q2 | GET+POST /q3
└── api.php     # POST /api/whatsapp/parse | POST /api/top/calculate

tests/Unit/
├── TopCalculatorTest.php
└── WhatsAppParserTest.php

docs/
├── q2_database_design.md
└── q3_top_additional_answers.md
```

---

**Stack:** Laravel 13 · PHP 8.3 · MySQL 9 · PHPUnit 12 · Tailwind CSS (CDN)
