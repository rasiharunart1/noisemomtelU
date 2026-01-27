<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true' }" x-bind:class="{ 'dark': darkMode }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Audio FFT Dashboard') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="font-sans antialiased" x-init="$watch('darkMode', val => localStorage.setItem('darkMode', val))">
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 transition-colors duration-300"
         x-bind:class="darkMode ? 'bg-gradient-dark' : 'bg-gradient-purple'">
        
        <!-- Logo & Dark Mode Toggle -->
        <div class="flex flex-col items-center justify-center mb-8">
            <div class="flex items-center space-x-4 mb-4">
                <div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center shadow-2xl shadow-red-500/50 animate-pulse-red p-2">
                    <x-application-logo class="w-full h-full" />
                </div>
                
                <!-- Dark Mode Toggle -->
                <button @click="darkMode = !darkMode" 
                        class="p-3 rounded-lg glass-card hover:scale-110 transition-transform text-2xl hover:border-red-500/40 text-gray-700 dark:text-white">
                    <!-- Moon Icon -->
                    <svg x-show="!darkMode" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                    <!-- Sun Icon -->
                    <svg x-show="darkMode" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </button>
            </div>
            
            <h1 class="text-3xl font-bold telkom-logo-text mb-1">
                NOISEMON
            </h1>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Telkom University - Audio FFT Monitoring System
            </p>
        </div>

        <!-- Auth Card -->
        <div class="w-full sm:max-w-md glass-card p-8 rounded-2xl shadow-2xl">
            {{ $slot }}
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                © 2025 Telkom University. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>
