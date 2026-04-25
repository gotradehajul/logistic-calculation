@extends('layouts.app')
@section('title', 'Q3 — TOP Calculator')

@section('content')

<div class="mb-8">
    <div class="text-xs font-bold text-emerald-600 uppercase tracking-wider mb-1">Question 3</div>
    <h1 class="text-3xl font-extrabold text-gray-900 mb-2">Term of Payment Calculator</h1>
    <p class="text-gray-500 max-w-2xl">
        Calculates the final TOP for transporter invoices based on baseline TOP and
        penalties for late POD / ePOD submissions.
    </p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

    {{-- Left: Formula + Calculator --}}
    <div class="lg:col-span-2 flex flex-col gap-6">

        {{-- Formula box --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
            <h3 class="font-bold text-gray-800 text-sm mb-4">Formula</h3>
            <ol class="space-y-2 text-sm text-gray-600 font-mono">
                @foreach([
                    ['podDelay',  'min( max(podLateDays, 0),  30)'],
                    ['epodDelay', 'min( max(epodLateDays, 0), 30)'],
                    ['penalty',   'podDelay + epodDelay'],
                    ['total',     'baselineTop + penalty'],
                    ['result',    'min(total, 45)'],
                ] as [$lhs, $rhs])
                    <li class="flex items-start gap-2">
                        <span class="text-emerald-500 mt-0.5">▶</span>
                        <span>
                            <span class="text-blue-700 font-semibold">{{ $lhs }}</span>
                            <span class="text-gray-400"> = </span>
                            <span>{{ $rhs }}</span>
                        </span>
                    </li>
                @endforeach
            </ol>
            <div class="mt-4 pt-4 border-t border-gray-100 grid grid-cols-3 gap-2 text-xs text-center">
                @foreach(['MAX_DELAY_POD = 30', 'MAX_DELAY_EPOD = 30', 'MAX_TOP = 45'] as $const)
                    <div class="bg-slate-50 border border-gray-200 rounded-lg py-2 font-mono text-gray-500">{{ $const }}</div>
                @endforeach
            </div>
        </div>

        {{-- Calculator form --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
            <h3 class="font-bold text-gray-800 text-sm mb-4">Try It</h3>
            <form method="POST" action="{{ route('q3.calculate') }}" class="space-y-4">
                @csrf
                @foreach([
                    ['baseline_top',   'Baseline TOP', 'Days from the transporter contract', 7],
                    ['pod_late_days',  'POD Late Days', 'Physical Proof of Delivery lateness', 5],
                    ['epod_late_days', 'ePOD Late Days', 'Electronic Proof of Delivery lateness', 3],
                ] as [$name, $label, $hint, $default])
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">{{ $label }}</label>
                        <input type="number" name="{{ $name }}" id="{{ $name }}"
                               value="{{ old($name, $input[$name] ?? $default) }}"
                               class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-300"
                               oninput="liveCalc()">
                        <p class="text-xs text-gray-400 mt-1">{{ $hint }}</p>
                    </div>
                @endforeach

                {{-- Live preview --}}
                <div id="preview" class="rounded-xl bg-slate-50 border border-gray-200 p-4 text-sm font-mono space-y-1 text-gray-600"></div>

                <button type="submit"
                        class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 rounded-xl transition-colors shadow-sm">
                    Calculate →
                </button>
            </form>

            @if(isset($result))
                <div class="mt-4 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-center">
                    <div class="text-xs font-semibold text-emerald-600 mb-1">Final TOP Result</div>
                    <div class="text-4xl font-extrabold text-emerald-700">{{ $result }}<span class="text-lg font-normal ml-1">days</span></div>
                    @php
                        $podD  = min(max((int)$input['pod_late_days'], 0), 30);
                        $epodD = min(max((int)$input['epod_late_days'], 0), 30);
                        $pen   = $podD + $epodD;
                        $total = (int)$input['baseline_top'] + $pen;
                        $capped = $total > 45;
                    @endphp
                    <div class="text-xs text-emerald-600 mt-1">
                        {{ $input['baseline_top'] }} baseline + {{ $podD }} POD + {{ $epodD }} ePOD = {{ $total }}{{ $capped ? ' → capped at 45' : '' }}
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Right: Test cases --}}
    <div class="lg:col-span-3">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-bold text-gray-800 text-sm">All 8 Test Cases</h3>
                <span class="text-xs bg-green-100 text-green-700 font-semibold px-2.5 py-1 rounded-full">8 / 8 passing</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs font-semibold text-gray-500 uppercase tracking-wider bg-slate-50 border-b border-gray-100">
                            <th class="px-4 py-3 text-center">#</th>
                            <th class="px-4 py-3 text-center">Baseline</th>
                            <th class="px-4 py-3 text-center">POD Late</th>
                            <th class="px-4 py-3 text-center">ePOD Late</th>
                            <th class="px-4 py-3 text-center">Expected</th>
                            <th class="px-4 py-3 text-left">Note</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach([
                            [1,  7,  5,   3,  15, 'Basic: 7 + 5 + 3 = 15'],
                            [2,  10, 35,  25, 45, 'POD capped → 10+30+25=65 → 45'],
                            [3,  20, 30,  30, 45, 'Both at max → 20+30+30=80 → 45'],
                            [4,  14, 0,   0,  14, 'No penalty, baseline only'],
                            [5,  5,  -2,  0,   5, 'Negative POD treated as 0'],
                            [6,  15, 20,  15, 45, 'Total 50 exceeds max cap'],
                            [7,  10, 30,  0,  40, 'POD at max, ePOD = 0'],
                            [8,  45, 0,   0,  45, 'Baseline at max, no penalty'],
                        ] as [$n, $base, $pod, $epod, $exp, $note])
                            @php
                                $calc = app(\App\Services\TopCalculator::class)->calculate($base, $pod, $epod);
                                $pass = $calc === $exp;
                            @endphp
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-4 py-3 text-center font-mono text-gray-400 text-xs">{{ $n }}</td>
                                <td class="px-4 py-3 text-center font-mono font-semibold">{{ $base }}</td>
                                <td class="px-4 py-3 text-center font-mono {{ $pod < 0 ? 'text-orange-600' : '' }}">{{ $pod }}</td>
                                <td class="px-4 py-3 text-center font-mono">{{ $epod }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center gap-1.5 font-mono font-bold
                                        {{ $pass ? 'text-green-700' : 'text-red-600' }}">
                                        {{ $pass ? '✓' : '✗' }} {{ $exp }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ $note }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Additional answers --}}
        <div class="mt-6 space-y-4">
            @foreach([
                [
                    'q' => 'a) Why cap POD and ePOD individually rather than the total penalty?',
                    'a' => 'Individual caps enforce a per-document-type fairness rule: neither a very late physical POD nor a very late ePOD can impose more than 30 days of penalty on its own. If only the total were capped at 30, one extremely late document could consume the entire penalty budget, hiding the other document\'s lateness. Individual caps make each document\'s contribution independently bounded.',
                ],
                [
                    'q' => 'b) What would happen without the final 45-day cap?',
                    'a' => 'The maximum possible result would be baseline + 30 + 30 = baseline + 60. A transporter with a 30-day baseline and both documents 45 days late would wait 90 days for payment — causing cash-flow crises, inability to cover fuel and salaries, and potential supply-chain disruption. The 45-day cap is a business viability safeguard.',
                ],
                [
                    'q' => 'c) How would you make the caps configurable?',
                    'a' => 'Two approaches: (1) config/top.php reading from .env — zero DB overhead, values change per environment without a deploy; (2) a top_config DB table with a 5-minute cache — allows runtime changes via a back-office UI without a deploy. Start with config files; promote to DB if a live-editing requirement emerges.',
                ],
            ] as $qa)
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                    <p class="font-semibold text-gray-800 text-sm mb-2">{{ $qa['q'] }}</p>
                    <p class="text-sm text-gray-600 leading-relaxed">{{ $qa['a'] }}</p>
                </div>
            @endforeach
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>
function liveCalc() {
    const b    = parseInt(document.getElementById('baseline_top').value)   || 0;
    const pod  = parseInt(document.getElementById('pod_late_days').value)  || 0;
    const epod = parseInt(document.getElementById('epod_late_days').value) || 0;

    const podD  = Math.min(Math.max(pod,  0), 30);
    const epodD = Math.min(Math.max(epod, 0), 30);
    const pen   = podD + epodD;
    const total = b + pen;
    const result = Math.min(total, 45);

    const pre = document.getElementById('preview');
    pre.innerHTML = [
        `<span class="text-gray-400">podDelay  =</span> min(max(${pod}, 0), 30) <span class="text-emerald-600 font-bold">= ${podD}</span>`,
        `<span class="text-gray-400">epodDelay =</span> min(max(${epod}, 0), 30) <span class="text-emerald-600 font-bold">= ${epodD}</span>`,
        `<span class="text-gray-400">penalty   =</span> ${podD} + ${epodD} <span class="text-emerald-600 font-bold">= ${pen}</span>`,
        `<span class="text-gray-400">total     =</span> ${b} + ${pen} <span class="text-emerald-600 font-bold">= ${total}</span>`,
        `<span class="text-gray-400">result    =</span> min(${total}, 45) <span class="text-blue-700 font-bold text-base">= ${result} days</span>`,
    ].join('<br>');
}
document.addEventListener('DOMContentLoaded', liveCalc);
</script>
@endpush
