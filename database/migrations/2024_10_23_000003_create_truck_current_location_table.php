<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalised "current location" table.
 *
 * WHY THIS EXISTS:
 *   Finding the latest row per truck from location_history (tens of millions of rows)
 *   requires either a correlated subquery or a GROUP BY + MAX(timestamp) — both
 *   scan a large fraction of the table even with indexes.
 *   By maintaining one row per truck here (upserted on every GPS ping), the
 *   radius-search query only touches this small table (≤ #trucks rows) and can
 *   use a simple B-tree index on (latitude, longitude) for bounding-box filtering.
 *
 * MAINTENANCE:
 *   Update this table inside a DB transaction whenever a new row is inserted into
 *   location_history (e.g. via a DB trigger or application service).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('truck_current_location', function (Blueprint $table) {
            // One row per truck; truck_id is both PK and FK
            $table->unsignedInteger('truck_id')->primary();

            $table->dateTime('timestamp');
            $table->decimal('latitude',  10, 7);
            $table->decimal('longitude', 11, 7);

            // Composite index on (latitude, longitude) enables the bounding-box
            // WHERE clause to exploit the B-tree index rather than doing a full scan.
            // MySQL can use range scans on the leading column (latitude), which
            // quickly narrows the candidate set before applying the longitude filter.
            $table->index(['latitude', 'longitude'], 'idx_current_loc_lat_lng');

            $table->foreign('truck_id')
                  ->references('truck_id')
                  ->on('trucks')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('truck_current_location');
    }
};
