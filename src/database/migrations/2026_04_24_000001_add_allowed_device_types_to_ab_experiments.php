<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ab_experiments', function (Blueprint $table) {
            // NULL = all device types allowed (backward compatible).
            // Populated with subset of ['mobile','tablet','desktop'] = gated.
            $table->json('allowed_device_types')->nullable()->after('targeting_rules');
        });
    }

    public function down(): void
    {
        Schema::table('ab_experiments', function (Blueprint $table) {
            $table->dropColumn('allowed_device_types');
        });
    }
};
