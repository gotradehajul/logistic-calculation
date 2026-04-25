<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_history', function (Blueprint $table) {
            // BIGINT for millions-to-tens-of-millions of rows
            $table->id();

            $table->unsignedInteger('truck_id');

            // DATETIME (not TIMESTAMP) avoids the 2038 problem and timezone
            // conversion surprises; store in UTC at the application layer.
            $table->dateTime('timestamp');

            // DECIMAL(10,7): 7 decimal places ≈ 1 cm precision at the equator,
            // which is more than enough for GPS coordinates.
            // Ranges: latitude [-90, 90], longitude [-180, 180].
            $table->decimal('latitude',  10, 7);
            $table->decimal('longitude', 11, 7);

            // Address is human-readable text; nullable since reverse-geocoding
            // may not always be available.
            // On MySQL: use utf8mb4_unicode_ci collation at table level.
            $table->string('address', 500)->nullable();

            // Composite index on (truck_id, timestamp DESC) is the key index:
            //   - Satisfies "latest record per truck" queries (ORDER BY timestamp DESC LIMIT 1)
            //   - Also covers range queries for a truck's history over a time period
            $table->index(['truck_id', 'timestamp'], 'idx_lh_truck_ts');

            $table->foreign('truck_id')
                  ->references('truck_id')
                  ->on('trucks')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_history');
    }
};
