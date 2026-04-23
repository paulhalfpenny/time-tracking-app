<?php

namespace App\Domain\Billing;

final readonly class RateResolution
{
    public function __construct(
        public bool $isBillable,
        public ?float $rateSnapshot,
        public float $billableAmount = 0.0,
    ) {}
}
