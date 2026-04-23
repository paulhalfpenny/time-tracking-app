<?php

namespace App\Domain\Reporting;

final readonly class TotalsDto
{
    public function __construct(
        public float $totalHours,
        public float $billableHours,
        public float $billableAmount,
        public float $uninvoicedAmount,
        public float $billablePercent,
    ) {}

    public static function empty(): self
    {
        return new self(0.0, 0.0, 0.0, 0.0, 0.0);
    }
}
