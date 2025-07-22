<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ab_experiments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->json('variants'); // {"control": 50, "variant_a": 25, "variant_b": 25}
            $table->boolean('is_active')->default(true);
            $table->integer('traffic_allocation')->default(100); // Percentage of users to include
            $table->json('target_applications')->default('["motus", "apollo", "olympus"]'); // Which apps to run in
            $table->json('success_metrics')->nullable(); // ["conversion", "click_rate", "time_on_page"]
            $table->json('custom_events')->nullable(); // Custom tracking events
            $table->integer('minimum_sample_size')->default(100);
            $table->decimal('confidence_level', 5, 2)->default(95.0); // Statistical confidence
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->json('targeting_rules')->nullable(); // For advanced targeting
            $table->string('status')->default('draft'); // draft, running, paused, completed
            $table->timestamps();

            $table->index(['name', 'is_active']);
            $table->index(['status', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('ab_experiments');
    }
};