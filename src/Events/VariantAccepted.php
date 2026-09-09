<?php

namespace Homemove\AbTesting\Events;

use Homemove\AbTesting\Models\Experiment;
use Illuminate\Foundation\Events\Dispatchable;

class VariantAccepted
{
    use Dispatchable;

    public function __construct(
        public Experiment $experiment,
        public array $report,
    ) {
    }
}
