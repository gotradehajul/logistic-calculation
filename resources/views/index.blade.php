@extends('layouts.app')
@section('title', 'Overview')

@section('content')

{{-- Hero --}}
<div class="text-center mb-14">
    <div class="inline-flex items-center gap-2 bg-blue-50 text-blue-700 text-xs font-semibold px-3 py-1 rounded-full mb-4">
        Backend Engineer · Take-Home Quiz
    </div>
    <h1 class="text-4xl font-extrabold text-gray-900 mb-3 tracking-tight">Logistic Calculation</h1>
    <p class="text-gray-500 max-w-xl mx-auto leading-relaxed">
        Three engineering challenges implemented in PHP 8.3 / Laravel 13:
        a WhatsApp message parser, a geospatial database design, and a payment term calculator.
    </p>
</div>

{{-- Cards --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">

    {{-- Q1 --}}
    <a href="{{ route('q1') }}"
       class="group bg-white rounded-2xl border border-gray-200 p-8 shadow-sm hover:shadow-md hover:border-blue-300 transition-all flex flex-col gap-4">
        <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center text-2xl">📱</div>
        <div>
            <div class="text-xs font-bold text-blue-500 uppercase tracking-wider mb-1">Question 1</div>
            <h2 class="text-xl font-bold text-gray-900 mb-2">WhatsApp Message Parser</h2>
            <p class="text-sm text-gray-500 leading-relaxed">
                Parses raw WhatsApp logistics messages into structured JSON — handling
                Indonesian dates, multi-destination cargo lines, PO dates, and formatting markers.
            </p>
        </div>
        <div class="mt-auto flex flex-wrap gap-2 text-xs">
            <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded-full">Regex</span>
            <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded-full">State machine</span>
            <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded-full">Date parsing</span>
        </div>
        <span class="text-blue-600 text-sm font-semibold group-hover:translate-x-1 transition-transform inline-block">
            Try the parser →
        </span>
    </a>

    {{-- Q2 --}}
    <a href="{{ route('q2') }}"
       class="group bg-white rounded-2xl border border-gray-200 p-8 shadow-sm hover:shadow-md hover:border-purple-300 transition-all flex flex-col gap-4">
        <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center text-2xl">🗄️</div>
        <div>
            <div class="text-xs font-bold text-purple-500 uppercase tracking-wider mb-1">Question 2</div>
            <h2 class="text-xl font-bold text-gray-900 mb-2">Database Design</h2>
            <p class="text-sm text-gray-500 leading-relaxed">
                MySQL/MariaDB schema for finding trucks within a given radius — with
                a denormalised current-location table, B-tree bounding-box indexing,
                and a Haversine radius query.
            </p>
        </div>
        <div class="mt-auto flex flex-wrap gap-2 text-xs">
            <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded-full">MySQL</span>
            <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded-full">Indexing</span>
            <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded-full">Haversine</span>
        </div>
        <span class="text-purple-600 text-sm font-semibold group-hover:translate-x-1 transition-transform inline-block">
            View schema →
        </span>
    </a>

    {{-- Q3 --}}
    <a href="{{ route('q3') }}"
       class="group bg-white rounded-2xl border border-gray-200 p-8 shadow-sm hover:shadow-md hover:border-emerald-300 transition-all flex flex-col gap-4">
        <div class="w-12 h-12 rounded-xl bg-emerald-100 flex items-center justify-center text-2xl">🧮</div>
        <div>
            <div class="text-xs font-bold text-emerald-600 uppercase tracking-wider mb-1">Question 3</div>
            <h2 class="text-xl font-bold text-gray-900 mb-2">TOP Calculator</h2>
            <p class="text-sm text-gray-500 leading-relaxed">
                Calculates the Term of Payment for transporter invoices — applying
                individual per-type delay caps and a final 45-day ceiling, with all
                8 specified test cases passing.
            </p>
        </div>
        <div class="mt-auto flex flex-wrap gap-2 text-xs">
            <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded-full">Business logic</span>
            <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded-full">PHPUnit</span>
            <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded-full">Edge cases</span>
        </div>
        <span class="text-emerald-600 text-sm font-semibold group-hover:translate-x-1 transition-transform inline-block">
            Open calculator →
        </span>
    </a>

</div>

{{-- Tech stack --}}
<div class="mt-12 bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4">Tech Stack</h3>
    <div class="flex flex-wrap gap-3 text-sm text-gray-700">
        @foreach(['Laravel 13', 'PHP 8.3', 'MySQL 9', 'PHPUnit 12', 'Tailwind CSS'] as $tech)
            <span class="flex items-center gap-1.5 bg-slate-50 border border-gray-200 px-3 py-1.5 rounded-lg font-medium">
                <span class="w-2 h-2 rounded-full bg-green-400"></span>{{ $tech }}
            </span>
        @endforeach
    </div>
</div>

@endsection
