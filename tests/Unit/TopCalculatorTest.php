<?php

namespace Tests\Unit;

use App\Services\TopCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TopCalculatorTest extends TestCase
{
    private TopCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new TopCalculator();
    }

    #[DataProvider('topCases')]
    public function test_calculate(int $baseline, int $pod, int $epod, int $expected): void
    {
        $this->assertSame($expected, $this->calculator->calculate($baseline, $pod, $epod));
    }

    public static function topCases(): array
    {
        return [
            // case 1: basic calculation — no caps hit
            'basic'                          => [7,  5,   3,  15],
            // case 2: POD capped at 30, total exceeds 45 → capped at 45
            'pod_capped_total_over_max'       => [10, 35,  25, 45],
            // case 3: both delays at max (30+30=60), total 80 → capped at 45
            'both_at_max'                    => [20, 30,  30, 45],
            // case 4: no penalty — baseline only
            'no_penalty'                     => [14, 0,   0,  14],
            // case 5: negative POD treated as 0
            'negative_pod'                   => [5,  -2,  0,   5],
            // case 6: total exceeds max cap
            'total_over_max'                 => [15, 20,  15, 45],
            // case 7: POD at max, ePOD zero — total 40, under cap
            'pod_max_epod_zero'              => [10, 30,  0,  40],
            // case 8: baseline already at max, no penalty
            'baseline_at_max'                => [45, 0,   0,  45],
        ];
    }

    public function test_both_negative_delays_treated_as_zero(): void
    {
        $this->assertSame(10, $this->calculator->calculate(10, -5, -10));
    }

    public function test_large_negative_delays_do_not_reduce_baseline(): void
    {
        $this->assertSame(7, $this->calculator->calculate(7, -100, -100));
    }
}
