<?php

namespace Homemove\AbTesting\Tests\Unit;

use Homemove\AbTesting\Services\AbTestService;
use Homemove\AbTesting\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Two first-visit requests for the same visitor can both pass the
 * "already assigned?" check and both try to insert. The unique index on
 * (experiment_id, user_id) makes the loser fail — and any retry that
 * re-computes could hand the same visitor a second variant.
 *
 * The race is reproduced deterministically: a query hook inserts the
 * competing row the moment the first request's INSERT is about to run,
 * i.e. between its SELECT and its INSERT. Before v1.7.1 this threw a
 * UniqueConstraintViolationException.
 */
class AssignmentRaceTest extends TestCase
{
    protected AbTestService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AbTestService();
        session()->flush();
        $_COOKIE = [];
    }

    /** @test */
    public function a_concurrent_first_assignment_for_the_same_visitor_returns_the_winning_variant_without_error()
    {
        $experimentId = DB::table('ab_experiments')->insertGetId([
            'name' => 'race_test',
            'variants' => json_encode(['control' => 50, 'variant_a' => 50]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // The "other request" wins the race with an arm the hash would NOT pick,
        // so returning our own computed arm would be visibly wrong.
        $ours = (hexdec(substr(md5('race_test' . 'racer'), 0, 8)) % 100) + 1 <= 50 ? 'control' : 'variant_a';
        $theirs = $ours === 'control' ? 'variant_a' : 'control';

        $injected = false;
        DB::beforeExecuting(function ($query) use (&$injected, $experimentId, $theirs) {
            if ($injected || !str_contains(strtolower($query), 'insert') || !str_contains($query, 'ab_user_assignments')) {
                return;
            }
            $injected = true;
            DB::table('ab_user_assignments')->insert([
                'experiment_id' => $experimentId,
                'user_id' => 'racer',
                'variant' => $theirs,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $variant = $this->service->variant('race_test', 'racer');

        $this->assertTrue($injected, 'the competing insert should have been injected');
        $this->assertSame($theirs, $variant, 'the loser must adopt the winning row, never its own computation');
        $this->assertSame(1, DB::table('ab_user_assignments')->where('experiment_id', $experimentId)->where('user_id', 'racer')->count());
    }
}
