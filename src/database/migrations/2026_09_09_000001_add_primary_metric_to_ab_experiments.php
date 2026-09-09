<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarded: an earlier combined migration may already have added this.
        if (Schema::hasColumn('ab_experiments', 'primary_metric')) {
            return;
        }

        Schema::table('ab_experiments', function (Blueprint $table) {
            // Which tracked event the dashboard treats as "the conversion".
            $table->string('primary_metric')->nullable()->after('success_metrics');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('ab_experiments', 'primary_metric')) {
            return;
        }

        Schema::table('ab_experiments', function (Blueprint $table) {
            $table->dropColumn('primary_metric');
        });
    }
};
