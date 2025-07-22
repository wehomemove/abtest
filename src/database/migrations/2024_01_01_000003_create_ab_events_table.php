<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ab_events', function (Blueprint $table) {
            $table->id();
            $table->string('experiment_name');
            $table->string('user_id');
            $table->string('variant');
            $table->string('event_name')->default('conversion');
            $table->json('properties')->nullable();
            $table->timestamp('event_time')->useCurrent();
            $table->timestamps();

            $table->index(['experiment_name', 'variant', 'event_name']);
            $table->index(['experiment_name', 'user_id']);
            $table->index('event_time');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ab_events');
    }
};