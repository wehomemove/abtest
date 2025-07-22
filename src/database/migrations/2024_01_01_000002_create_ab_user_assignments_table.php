<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ab_user_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('experiment_name');
            $table->string('user_id'); // Can be session ID, user ID, etc.
            $table->string('variant');
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamps();

            $table->unique(['experiment_name', 'user_id']);
            $table->index(['experiment_name', 'variant']);
            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ab_user_assignments');
    }
};