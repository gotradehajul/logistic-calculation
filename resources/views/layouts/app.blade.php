<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Backend Quiz') — Logistic Calculation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css" rel="stylesheet"/>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js" defer></script>
    <style>
        pre[class*="language-"] { border-radius: 0.5rem; font-size: 0.82rem; }
        .nav-link { @apply text-sm text-gray-500 hover:text-blue-600 transition-colors px-3 py-1 rounded-md hover:bg-blue-50; }
        .nav-link.active { @apply text-blue-600 bg-blue-50 font-medium; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen text-gray-800">

    <nav class="bg-white border-b border-gray-200 sticky top-0 z-10 shadow-sm">
        <div class="max-w-6xl mx-auto px-6 h-14 flex items-center justify-between">
            <a href="/" class="flex items-center gap-2 font-bold text-gray-900 text-base">
                <span class="text-blue-600">⬡</span>
                Logistic Quiz
            </a>
            <div class="flex items-center gap-1">
                <a href="{{ route('q1') }}" class="nav-link {{ request()->routeIs('q1') ? 'active' : '' }}">Q1 Parser</a>
                <a href="{{ route('q2') }}" class="nav-link {{ request()->routeIs('q2') ? 'active' : '' }}">Q2 Database</a>
                <a href="{{ route('q3') }}" class="nav-link {{ request()->routeIs('q3') ? 'active' : '' }}">Q3 Calculator</a>
            </div>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-6 py-10">
        @yield('content')
    </main>

    <footer class="border-t border-gray-200 mt-16 py-6 text-center text-xs text-gray-400">
        Backend Engineer Take-Home Quiz &mdash; Laravel {{ app()->version() }} &bull; PHP {{ PHP_VERSION }}
    </footer>

    @stack('scripts')
</body>
</html>
