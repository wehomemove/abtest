<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCountToAbEventsTable extends Migration
{
    public function up()
    {
        Schema::table('ab_events', function (Blueprint $table) {
            $table->unsignedInteger('count')->default(1)->after('properties');
            
            // Add unique constraint to prevent duplicate entries for same user/experiment/event/variant combination
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