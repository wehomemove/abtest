<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ab_experiments', function (Blueprint $table) {
            // Which tracked event the dashboard treats as "the conversion".
            $table->string('primary_metric')->nullable()->after('success_metrics');
            // Accept-variant lifecycle: winner rolled out to 100% of traffic.
            $table->string('accepted_variant')->nullable()->after('status');
            $table->timestamp('accepted_at')->nullable()->after('accepted_variant');
            // Snapshot of variants/status/is_active for a reversible reopen.
            $table->json('pre_acceptance')->nullable()->after('accepted_at');
        });

        Schema::create('ab_acceptance_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experiment_id')->constrained('ab_experiments')->cascadeOnDelete();
            $table->string('accepted_variant');
            $table->string('status')->default('pending'); // pending | completed | failed
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index('experiment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ab_acceptance_reports');

        Schema::table('ab_experiments', function (Blueprint $table) {
            $table->dropColumn(['primary_metric', 'accepted_variant', 'accepted_at', 'pre_acceptance']);
        });
    }
};
