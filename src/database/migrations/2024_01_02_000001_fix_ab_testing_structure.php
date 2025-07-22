<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // First, let's add the experiment_id column to ab_events
        Schema::table('ab_events', function (Blueprint $table) {
            $table->unsignedBigInteger('experiment_id')->nullable()->after('id');
            $table->foreign('experiment_id')->references('id')->on('ab_experiments')->onDelete('cascade');
        });

        // Update existing records to use experiment_id instead of experiment_name
        $experiments = DB::table('ab_experiments')->get();
        foreach ($experiments as $experiment) {
            DB::table('ab_events')
                ->where('experiment_name', $experiment->name)
                ->update(['experiment_id' => $experiment->id]);
        }

        // Now we can drop the experiment_name column and update indexes
        Schema::table('ab_events', function (Blueprint $table) {
            // Drop old indexes if they exist
            $indexes = DB::select("SELECT indexname FROM pg_indexes WHERE tablename = 'ab_events' AND schemaname = 'public'");
            $indexNames = array_column($indexes, 'indexname');
            
            if (in_array('ab_events_experiment_name_variant_event_name_index', $indexNames)) {
                $table->dropIndex(['experiment_name', 'variant', 'event_name']);
            }
            if (in_array('ab_events_experiment_name_user_id_index', $indexNames)) {
                $table->dropIndex(['experiment_name', 'user_id']);
            }
            
            // Drop the experiment_name column if it exists
            if (Schema::hasColumn('ab_events', 'experiment_name')) {
                $table->dropColumn('experiment_name');
            }
        });

        // Add new indexes
        Schema::table('ab_events', function (Blueprint $table) {
            $table->index(['experiment_id', 'variant', 'event_name']);
            $table->index(['experiment_id', 'user_id']);
        });

        // Do the same for ab_user_assignments
        Schema::table('ab_user_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('experiment_id')->nullable()->after('id');
            $table->foreign('experiment_id')->references('id')->on('ab_experiments')->onDelete('cascade');
        });

        // Update existing user assignments
        foreach ($experiments as $experiment) {
            DB::table('ab_user_assignments')
                ->where('experiment_name', $experiment->name)
                ->update(['experiment_id' => $experiment->id]);
        }

        Schema::table('ab_user_assignments', function (Blueprint $table) {
            // Drop old indexes if they exist
            $indexes = DB::select("SELECT indexname FROM pg_indexes WHERE tablename = 'ab_user_assignments' AND schemaname = 'public'");
            $indexNames = array_column($indexes, 'indexname');
            
            if (in_array('ab_user_assignments_experiment_name_user_id_index', $indexNames)) {
                $table->dropIndex(['experiment_name', 'user_id']);
            }
            
            // Drop the experiment_name column if it exists
            if (Schema::hasColumn('ab_user_assignments', 'experiment_name')) {
                $table->dropColumn('experiment_name');
            }
            
            // Add new index
            $table->index(['experiment_id', 'user_id']);
        });
    }

    public function down()
    {
        // Add back the experiment_name columns
        Schema::table('ab_events', function (Blueprint $table) {
            $table->string('experiment_name')->after('id');
        });

        Schema::table('ab_user_assignments', function (Blueprint $table) {
            $table->string('experiment_name')->after('id');
        });

        // Restore experiment names from experiment_id
        $experiments = DB::table('ab_experiments')->get();
        foreach ($experiments as $experiment) {
            DB::table('ab_events')
                ->where('experiment_id', $experiment->id)
                ->update(['experiment_name' => $experiment->name]);
                
            DB::table('ab_user_assignments')
                ->where('experiment_id', $experiment->id)
                ->update(['experiment_name' => $experiment->name]);
        }

        // Drop foreign keys and experiment_id columns
        Schema::table('ab_events', function (Blueprint $table) {
            $table->dropForeign(['experiment_id']);
            $table->dropIndex(['experiment_id', 'variant', 'event_name']);
            $table->dropIndex(['experiment_id', 'user_id']);
            $table->dropColumn('experiment_id');
            
            // Restore old indexes
            $table->index(['experiment_name', 'variant', 'event_name']);
            $table->index(['experiment_name', 'user_id']);
        });

        Schema::table('ab_user_assignments', function (Blueprint $table) {
            $table->dropForeign(['experiment_id']);
            $table->dropIndex(['experiment_id', 'user_id']);
            $table->dropColumn('experiment_id');
            
            // Restore old index
            $table->index(['experiment_name', 'user_id']);
        });
    }
};