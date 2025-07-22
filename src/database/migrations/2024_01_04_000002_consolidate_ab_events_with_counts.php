<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ConsolidateAbEventsWithCounts extends Migration
{
    public function up()
    {
        // First, add the count column
        if (!Schema::hasColumn('ab_events', 'count')) {
            Schema::table('ab_events', function (Blueprint $table) {
                $table->unsignedInteger('count')->default(1)->after('properties');
            });
        }

        // Consolidate duplicate entries by counting them
        DB::statement('
            CREATE TEMPORARY TABLE ab_events_consolidated AS
            SELECT 
                MIN(id) as id,
                experiment_id,
                user_id,
                event_name,
                variant,
                properties,
                COUNT(*) as count,
                MIN(created_at) as created_at,
                MAX(updated_at) as updated_at
            FROM ab_events
            GROUP BY experiment_id, user_id, event_name, variant, properties
        ');

        // Clear the original table
        DB::table('ab_events')->truncate();

        // Insert consolidated data
        DB::statement('
            INSERT INTO ab_events (experiment_id, user_id, event_name, variant, properties, count, created_at, updated_at)
            SELECT experiment_id, user_id, event_name, variant, properties, count, created_at, updated_at
            FROM ab_events_consolidated
        ');

        // Drop the temporary table
        DB::statement('DROP TABLE ab_events_consolidated');

        // Now add the unique constraint
        Schema::table('ab_events', function (Blueprint $table) {
            $table->unique(['experiment_id', 'user_id', 'event_name', 'variant'], 'ab_events_unique');
        });
    }

    public function down()
    {
        Schema::table('ab_events', function (Blueprint $table) {
            $table->dropUnique('ab_events_unique');
            $table->dropColumn('count');
        });
    }
}