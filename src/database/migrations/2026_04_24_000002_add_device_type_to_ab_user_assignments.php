<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ab_user_assignments', function (Blueprint $table) {
            $table->string('device_type', 16)->nullable()->after('variant');
            $table->index(['experiment_id', 'device_type']);
        });
    }

    public function down(): void
    {
        Schema::table('ab_user_assignments', function (Blueprint $table) {
            $table->dropIndex(['experiment_id', 'device_type']);
            $table->dropColumn('device_type');
        });
    }
};
