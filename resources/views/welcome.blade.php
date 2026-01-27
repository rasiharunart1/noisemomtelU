<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Noisemon') }}</title>
        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />
        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased bg-gray-50 dark:bg-gray-900 selection:bg-red-500 selection:text-white relative overflow-x-hidden">
        
        <!-- Background Gradients -->
        <div class="fixed inset-0 z-0 pointer-events-none">
            <div class="absolute top-0 right-0 -mr-20 -mt-20 w-[600px] h-[600px] bg-red-500/10 rounded-full blur-3xl"></div>
            <div class="absolute bottom-0 left-0 -ml-20 -mb-20 w-[500px] h-[500px] bg-purple-500/10 rounded-full blur-3xl"></div>
        </div>

        <div class="relative z-10 min-h-screen flex flex-col justify-between">
            <!-- Navbar -->
            <nav class="w-full py-6 px-6 md:px-12 flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center shadow-lg shadow-red-500/20 p-1.5">
                        <x-application-logo class="w-full h-full" />
                    </div>
                    <div class="hidden md:block">
                        <h1 class="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-gray-900 to-gray-700 dark:from-white dark:to-gray-300">
                            NOISEMON
                        </h1>
                    </div>
                </div>

                <div class="flex items-center space-x-4">
                    @if (Route::has('login'))
                        @auth
                            <a href="{{ url('/dashboard') }}" class="font-semibold text-gray-600 hover:text-red-600 dark:text-gray-400 dark:hover:text-white transition-colors duration-200">
                                Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="font-semibold text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white transition-colors duration-200">
                                Log in
                            </a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-red-600 to-red-700 text-white font-medium shadow-lg shadow-red-500/30 hover:shadow-red-500/50 transform hover:-translate-y-0.5 transition-all duration-200">
                                    Register
                                </a>
                            @endif
                        @endauth
                    @endif
                </div>
            </nav>

            <!-- Hero Section -->
            <main class="flex-grow flex items-center justify-center px-6">
                <div class="max-w-7xl w-full grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                    <div class="space-y-8 text-center lg:text-left">
                        <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-red-100 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 text-sm font-medium">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
                            </span>
                            <span>Live Monitoring System</span>
                        </div>
                        
                        <h1 class="text-5xl md:text-6xl lg:text-7xl font-extrabold tracking-tight text-gray-900 dark:text-white leading-tight">
                            Monitor Noise <br>
                            <span class="text-transparent bg-clip-text bg-gradient-to-r from-red-600 to-purple-600">
                                In Real-Time
                            </span>
                        </h1>
                        
                        <p class="text-lg md:text-xl text-gray-600 dark:text-gray-400 leading-relaxed max-w-2xl mx-auto lg:mx-0">
                            Advanced IoT-based acoustic monitoring system featuring real-time FFT analysis, device tracking, and insightful data visualization. Powered by Telkom University Purwokerto.
                        </p>

                        <div class="flex flex-col sm:flex-row items-center space-y-4 sm:space-y-0 sm:space-x-4 justify-center lg:justify-start">
                            <a href="{{ route('login') }}" class="w-full sm:w-auto px-8 py-4 rounded-xl bg-gray-900 dark:bg-white text-white dark:text-gray-900 font-bold text-lg shadow-xl shadow-gray-900/20 hover:shadow-gray-900/40 transform hover:-translate-y-1 transition-all duration-200 text-center">
                                Get Started
                            </a>
                            <a href="#features" class="w-full sm:w-auto px-8 py-4 rounded-xl glass-card border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white font-semibold text-lg hover:bg-gray-50 dark:hover:bg-white/5 transition-colors duration-200 text-center flex items-center justify-center space-x-2">
                                <span>Learn More</span>
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
                            </a>
                        </div>
                    </div>

                    <!-- Hero Visual -->
                    <div class="relative lg:h-[600px] flex items-center justify-center">
                        <div class="relative w-full max-w-lg aspect-square">
                            <!-- Decorative circles -->
                            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[120%] h-[120%] border border-gray-200 dark:border-white/5 rounded-full animate-[spin_60s_linear_infinite]"></div>
                            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[80%] h-[80%] border border-dashed border-gray-300 dark:border-white/10 rounded-full animate-[reverse-spin_40s_linear_infinite]"></div>
                            
                            <!-- Main Card -->
                            <div class="absolute inset-0 glass-card rounded-3xl border border-white/20 shadow-2xl overflow-hidden transform rotate-3 hover:rotate-0 transition-transform duration-500 group">
                                <div class="p-6 h-full flex flex-col">
                                    <div class="flex justify-between items-center mb-6">
                                        <div class="flex space-x-2">
                                            <div class="w-3 h-3 rounded-full bg-red-500"></div>
                                            <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                                            <div class="w-3 h-3 rounded-full bg-green-500"></div>
                                        </div>
                                        <div class="text-xs font-mono text-gray-500">Live Feed</div>
                                    </div>
                                    <!-- Pseudo Chart -->
                                    <div class="flex-1 flex items-end space-x-1 pb-4">
                                        @for ($i = 0; $i < 20; $i++)
                                            <div class="flex-1 bg-gradient-to-t from-red-600/80 to-purple-600/80 rounded-t-sm animate-pulse" 
                                                 style="height: {{ rand(20, 100) }}%; animation-delay: {{ $i * 100 }}ms"></div>
                                        @endfor
                                    </div>
                                    <div class="h-16 glass-card rounded-xl p-3 flex items-center justify-between">
                                        <div>
                                            <div class="text-xs text-gray-500">Current Level</div>
                                            <div class="text-xl font-bold dark:text-white">84.2 dB</div>
                                        </div>
                                        <div class="w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center text-red-600">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            <!-- Features Section -->
            <section id="features" class="py-20 px-6 bg-white/50 dark:bg-black/20 backdrop-blur-sm">
                <div class="max-w-7xl mx-auto">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <!-- Feature 1 -->
                        <div class="p-8 rounded-2xl bg-white dark:bg-gray-800 shadow-xl shadow-gray-200/50 dark:shadow-none border border-gray-100 dark:border-gray-700 hover:border-red-500/30 transition-colors">
                            <div class="w-14 h-14 rounded-xl bg-red-100 dark:bg-red-900/20 flex items-center justify-center text-red-600 mb-6">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                            </div>
                            <h3 class="text-xl font-bold mb-3 dark:text-white">Real-Time Analytics</h3>
                            <p class="text-gray-600 dark:text-gray-400">Monitor noise levels instantly with high-precision sensors and visualize data through dynamic charts.</p>
                        </div>
                        <!-- Feature 2 -->
                        <div class="p-8 rounded-2xl bg-white dark:bg-gray-800 shadow-xl shadow-gray-200/50 dark:shadow-none border border-gray-100 dark:border-gray-700 hover:border-purple-500/30 transition-colors">
                            <div class="w-14 h-14 rounded-xl bg-purple-100 dark:bg-purple-900/20 flex items-center justify-center text-purple-600 mb-6">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            </div>
                            <h3 class="text-xl font-bold mb-3 dark:text-white">Device Geo-Tagging</h3>
                            <p class="text-gray-600 dark:text-gray-400">Track device locations on an interactive map. Manage your sensor network efficiently.</p>
                        </div>
                        <!-- Feature 3 -->
                        <div class="p-8 rounded-2xl bg-white dark:bg-gray-800 shadow-xl shadow-gray-200/50 dark:shadow-none border border-gray-100 dark:border-gray-700 hover:border-blue-500/30 transition-colors">
                            <div class="w-14 h-14 rounded-xl bg-blue-100 dark:bg-blue-900/20 flex items-center justify-center text-blue-600 mb-6">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            </div>
                            <h3 class="text-xl font-bold mb-3 dark:text-white">Fast & Secure</h3>
                            <p class="text-gray-600 dark:text-gray-400">Built on modern tech stack ensuring high performance, security, and scalability for your data.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Footer -->
            <footer class="py-8 text-center text-sm text-gray-500 dark:text-gray-500 border-t border-gray-200 dark:border-white/5">
                <div class="flex flex-col items-center space-y-2">
                    <div class="flex items-center space-x-2">
                        <x-application-logo class="w-6 h-6 text-gray-400" />
                        <span class="font-semibold text-gray-700 dark:text-gray-300">NOISEMON</span>
                    </div>
                    <p>&copy; {{ date('Y') }} Telkom University Purwokerto. All rights reserved.</p>
                </div>
            </footer>
        </div>
    </body>
</html>
