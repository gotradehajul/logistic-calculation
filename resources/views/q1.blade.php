@extends('layouts.app')
@section('title', 'Q1 — WhatsApp Parser')

@section('content')

<div class="mb-8">
    <div class="text-xs font-bold text-blue-500 uppercase tracking-wider mb-1">Question 1</div>
    <h1 class="text-3xl font-extrabold text-gray-900 mb-2">WhatsApp Message Parser</h1>
    <p class="text-gray-500 max-w-2xl">
        Paste a raw WhatsApp logistics message and see it parsed into structured JSON.
        Load one of the four example messages to try instantly.
    </p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    {{-- Input panel --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm flex flex-col">
        <div class="px-6 pt-6 pb-3 border-b border-gray-100">
            <div class="flex items-center justify-between mb-3">
                <span class="font-semibold text-gray-800 text-sm">Raw Message</span>
                <div class="flex gap-1.5">
                    @foreach(range(1, 4) as $n)
                        <button onclick="loadExample({{ $n }})"
                                class="text-xs px-2.5 py-1 rounded-lg border border-gray-200 text-gray-500 hover:border-blue-400 hover:text-blue-600 transition-colors">
                            Msg {{ $n }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('q1.parse') }}" class="flex flex-col flex-1 px-6 pb-6 pt-4 gap-4">
            @csrf
            <textarea id="messageInput" name="message" rows="20"
                      class="w-full font-mono text-sm border border-gray-200 rounded-xl p-4 resize-none focus:outline-none focus:ring-2 focus:ring-blue-300 bg-slate-50 text-gray-800 leading-relaxed"
                      placeholder="Paste a raw WhatsApp message here…">{{ old('message', $message ?? '') }}</textarea>
            <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-5 rounded-xl transition-colors shadow-sm">
                Parse Message →
            </button>
        </form>
    </div>

    {{-- Output panel --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm flex flex-col">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <span class="font-semibold text-gray-800 text-sm">Parsed JSON</span>
            @if(isset($result))
                <span class="text-xs bg-green-100 text-green-700 font-semibold px-2.5 py-1 rounded-full">
                    {{ count($result['items']) }} item{{ count($result['items']) !== 1 ? 's' : '' }} parsed
                </span>
            @endif
        </div>

        <div class="flex-1 p-6 overflow-auto">
            @if(isset($result))
                <pre class="!m-0 !rounded-xl overflow-auto max-h-[520px]"><code class="language-json">{{ $json }}</code></pre>
            @else
                <div class="h-full flex flex-col items-center justify-center text-center text-gray-400 py-16">
                    <div class="text-5xl mb-4">📋</div>
                    <p class="text-sm">The parsed result will appear here.<br>Load an example message and click <strong>Parse</strong>.</p>
                </div>
            @endif
        </div>
    </div>

</div>

{{-- Field guide --}}
<div class="mt-6 bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
    <h3 class="font-semibold text-gray-800 mb-4 text-sm">Output Fields</h3>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
        @foreach([
            ['date', 'ISO 8601 planning date derived from the Indonesian date line'],
            ['origin', 'Warehouse / depot the shipment originates from'],
            ['items[].destinations', 'One or more delivery destinations split by "+"'],
            ['items[].volumeCbm', 'Cargo volume in cubic metres'],
            ['items[].unitCount', 'Number of truck units required'],
            ['items[].poDate', 'Optional PO date (same format as main date)'],
            ['items[].notes', 'Optional parenthetical note, e.g. "(Gudang Bayur)"'],
            ['safetyNote', 'Optional "Pastikan Driver…" safety reminder'],
        ] as [$field, $desc])
            <div class="bg-slate-50 rounded-xl p-3">
                <code class="text-xs text-blue-700 font-semibold block mb-1">{{ $field }}</code>
                <p class="text-xs text-gray-500 leading-relaxed">{{ $desc }}</p>
            </div>
        @endforeach
    </div>
</div>

@endsection

@push('scripts')
<script>
const examples = {!! $examples !!};

function loadExample(n) {
    document.getElementById('messageInput').value = examples[n] || '';
}
</script>
@endpush
