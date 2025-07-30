<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>A/B Testing Dashboard - @yield('title')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="bg-gray-50">
    <nav class="bg-gradient-to-r from-gray-900 via-blue-900 to-indigo-900 shadow-2xl border-b border-indigo-600">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-8">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 bg-gradient-to-br from-blue-400 to-purple-500 rounded-lg flex items-center justify-center">
                            <i class="fas fa-flask text-white text-sm"></i>
                        </div>
                        <h1 class="text-xl font-bold text-white">A/B Testing Dashboard</h1>
                    </div>
                    <div class="hidden md:flex items-center space-x-1">
                        <a href="{{ route('ab-testing.dashboard.index') }}" 
                           class="px-4 py-2 text-sm font-medium text-blue-100 hover:text-white hover:bg-blue-800 hover:bg-opacity-50 rounded-xl transition-all duration-200 {{ request()->routeIs('ab-testing.dashboard.index') ? 'bg-blue-800 bg-opacity-30 text-white' : '' }}">
                            <i class="fas fa-chart-bar mr-2"></i>Experiments
                        </a>
                        <a href="{{ route('ab-testing.dashboard.create') }}" 
                           class="px-4 py-2 text-sm font-medium text-blue-100 hover:text-white hover:bg-blue-800 hover:bg-opacity-50 rounded-xl transition-all duration-200 {{ request()->routeIs('ab-testing.dashboard.create') ? 'bg-blue-800 bg-opacity-30 text-white' : '' }}">
                            <i class="fas fa-plus mr-2"></i>Create
                        </a>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="hidden sm:flex items-center space-x-3">
                        <div class="text-xs text-blue-200">
                            <div class="flex items-center space-x-1">
                                <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                                <span>Live</span>
                            </div>
                        </div>
                    </div>
                    <a href="{{ route('ab-testing.dashboard.create') }}" 
                       class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-6 py-2 rounded-xl font-medium hover:from-blue-600 hover:to-purple-700 transform hover:scale-105 transition-all duration-200 shadow-lg hover:shadow-xl">
                        <i class="fas fa-plus mr-2"></i>New Experiment
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        @if(session('success'))
            <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                <ul class="list-disc ml-4">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>