<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trucks', function (Blueprint $table) {
            // UNSIGNED INT is sufficient for tens of thousands of trucks
            $table->increments('truck_id');

            // VARCHAR(20): Indonesian plates like "B 1234 ABC" fit in 15 chars;
            // 20 gives headroom.
            // On MySQL: set table-level utf8mb4_unicode_ci (see mysql config / Q2 design doc).
            // Unique index doubles as the fast look-up index for plate queries.
            $table->string('license_plate_number', 20)->unique('idx_trucks_plate');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trucks');
    }
};
