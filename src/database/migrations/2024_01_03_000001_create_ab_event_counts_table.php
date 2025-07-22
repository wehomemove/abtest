<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAbEventCountsTable extends Migration
{
    public function up()
    {
        Schema::create('ab_event_counts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('experiment_id');
            $table->string('user_id', 191);
            $table->string('event_name', 191);
            $table->string('variant', 191);
            $table->unsignedInteger('count')->default(1);
            $table->json('properties')->nullable();
            $table->timestamps();

            $table->foreign('experiment_id')->references('id')->on('ab_experiments')->onDelete('cascade');
            $table->unique(['experiment_id', 'user_id', 'event_name', 'variant'], 'ab_event_counts_unique');
            $table->index(['experiment_id', 'event_name']);
            $table->index(['user_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('ab_event_counts');
    }
}