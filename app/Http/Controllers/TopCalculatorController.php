<?php

namespace App\Http\Controllers;

use App\Services\TopCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TopCalculatorController extends Controller
{
    public function __construct(private readonly TopCalculator $calculator) {}

    /**
     * POST /api/top/calculate
     *
     * Body: { "baseline_top": 7, "pod_late_days": 5, "epod_late_days": 3 }
     */
    public function calculate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'baseline_top'   => ['required', 'integer'],
            'pod_late_days'  => ['required', 'integer'],
            'epod_late_days' => ['required', 'integer'],
        ]);

        $result = $this->calculator->calculate(
            $data['baseline_top'],
            $data['pod_late_days'],
            $data['epod_late_days']
        );

        return response()->json([
            'baseline_top'   => $data['baseline_top'],
            'pod_late_days'  => $data['pod_late_days'],
            'epod_late_days' => $data['epod_late_days'],
            'top_result'     => $result,
        ]);
    }
}
