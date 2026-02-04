<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true' }" x-bind:class="{ 'dark': darkMode }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'Audio FFT Dashboard') }}</title>
<link rel="icon" href="{{ asset('favicon.ico') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Cropper.js -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
    
    @stack('styles')
</head>
<body class="font-sans antialiased" x-init="$watch('darkMode', val => localStorage.setItem('darkMode', val))" x-data="{ showLogoutModal: false, mobileMenuOpen: false }">
    <div class="min-h-screen transition-colors duration-300" 
         x-bind:class="darkMode ? 'bg-gradient-dark' : 'bg-gradient-purple'">
        
        <!-- Mobile Sidebar Backdrop -->
        <div x-show="mobileMenuOpen" 
             @click="mobileMenuOpen = false"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-30 bg-black/60 backdrop-blur-sm sm:hidden" 
             style="display: none;">
        </div>

        <!-- Sidebar -->
        <aside class="fixed left-0 top-0 z-40 h-screen w-64 transition-transform sm:translate-x-0"
               x-bind:class="mobileMenuOpen ? 'translate-x-0' : '-translate-x-full'"
               @click.away="if(window.innerWidth < 640) mobileMenuOpen = false"
               aria-label="Sidebar">
            <!-- Floating Container -->
            <div class="h-full p-4">
                <!-- Glass Card Content -->
                <div class="h-full glass-card rounded-2xl flex flex-col overflow-hidden">
                    
                    <!-- Header: Logo & Toggle -->
                    <div class="p-4 flex items-center justify-between shrink-0">
                        <div class="flex items-center space-x-2 min-w-0">
                            <div class="w-10 h-10 bg-white rounded-lg flex flex-shrink-0 items-center justify-center shadow-lg shadow-red-500/50 p-1.5">
                                <x-application-logo class="w-full h-full" />
                            </div>
                            <div class="min-w-0 flex-1 hidden md:block">
                                <h1 class="text-base font-bold telkom-logo-text truncate">NOISEMON</h1>
                                <p class="text-[10px] text-gray-500 dark:text-gray-400 truncate">Telkom University Purwokerto</p>
                            </div>
                        </div>
                        
                        <!-- Mobile Close Button -->
                        <button @click="mobileMenuOpen = false" class="sm:hidden p-2 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>

                        <!-- Dark Mode Toggle -->
                        <button @click="darkMode = !darkMode" class="p-2 rounded-lg glass-card hover:scale-110 transition-transform hover:border-red-500/40 text-gray-700 dark:text-white flex-shrink-0 ml-1">
                            <svg x-show="!darkMode" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                            <svg x-show="darkMode" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                        </button>
                    </div>

                    <!-- Navigation Menu -->
                    <div class="flex-1 overflow-y-auto scrollbar-hide px-3 py-2">
                        <ul class="space-y-2 font-medium">
                            <li>
                                <a href="{{ route('dashboard') }}" 
                                   class="flex items-center p-3 rounded-lg {{ request()->routeIs('dashboard') ? 'bg-gradient-to-r from-red-600 to-red-700 text-white shadow-lg shadow-red-500/30' : 'text-gray-700 dark:text-gray-200 hover:bg-white/10 hover:border-red-500/30' }} transition-all group">
                                    <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                                    </svg>
                                    <span class="ml-3 truncate">Dashboard</span>
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('devices.index') }}" 
                                   class="flex items-center p-3 rounded-lg {{ request()->routeIs('devices.*') ? 'bg-gradient-to-r from-red-600 to-red-700 text-white shadow-lg shadow-red-500/30' : 'text-gray-700 dark:text-gray-200 hover:bg-white/10 hover:border-red-500/30' }} transition-all">
                                    <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M7 2a2 2 0 00-2 2v12a2 2 0 002 2h6a2 2 0 002-2V4a2 2 0 00-2-2H7zm3 14a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="ml-3 truncate">Devices</span>
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('logs.fft') }}" 
                                   class="flex items-center p-3 rounded-lg {{ request()->routeIs('logs.*') ? 'bg-gradient-to-r from-red-600 to-red-700 text-white shadow-lg shadow-red-500/30' : 'text-gray-700 dark:text-gray-200 hover:bg-white/10 hover:border-red-500/30' }} transition-all">
                                    <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                                        <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="ml-3 truncate">FFT Logs</span>
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('settings') }}" 
                                   class="flex items-center p-3 rounded-lg {{ request()->routeIs('settings') ? 'bg-gradient-to-r from-red-600 to-red-700 text-white shadow-lg shadow-red-500/30' : 'text-gray-700 dark:text-gray-200 hover:bg-white/10 hover:border-red-500/30' }} transition-all">
                                    <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="ml-3 truncate">Settings</span>
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('profile.edit') }}" 
                                   class="flex items-center p-3 rounded-lg {{ request()->routeIs('profile.edit') ? 'bg-gradient-to-r from-red-600 to-red-700 text-white shadow-lg shadow-red-500/30' : 'text-gray-700 dark:text-gray-200 hover:bg-white/10 hover:border-red-500/30' }} transition-all">
                                    <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="ml-3 truncate">Profile</span>
                                </a>
                            </li>
                        </ul>
                    </div>

                    <!-- User Profile -->
                    <div class="p-4 border-t border-white/10 shrink-0">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center min-w-0 mr-2">
                                <div class="w-8 h-8 rounded-full flex flex-shrink-0 items-center justify-center text-white font-bold text-xs shadow-md overflow-hidden">
                                    @if(Auth::user()->profile_photo_path)
                                        <img src="{{ asset('storage/' . Auth::user()->profile_photo_path) }}" alt="{{ Auth::user()->name }}" class="w-full h-full object-cover">
                                    @else
                                        <div class="w-full h-full bg-gradient-to-r from-purple-400 to-indigo-600 flex items-center justify-center">
                                            {{ substr(Auth::user()->name, 0, 1) }}
                                        </div>
                                    @endif
                                </div>
                                <div class="ml-2 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate" title="{{ Auth::user()->name }}">
                                        {{ Auth::user()->name }}
                                    </p>
                                    <p class="text-[10px] text-gray-500 dark:text-gray-400 truncate" title="{{ Auth::user()->email }}">
                                        {{ Auth::user()->email }}
                                    </p>
                                </div>
                            </div>
                            
                            <button @click="showLogoutModal = true" class="text-red-500 hover:text-red-700 p-1.5 hover:bg-red-50 rounded-lg transition-colors flex-shrink-0" title="Logout">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="sm:ml-64">
            <!-- Top Bar -->
            <div class="p-4">
                <div class="glass-card p-4 mb-4 rounded-2xl">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <!-- Mobile Menu Toggle -->
                            <button @click="mobileMenuOpen = true" class="sm:hidden mr-3 p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/10">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                            </button>
                            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ $header ?? 'Dashboard' }}
                            </h2>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <!-- Connection Status -->
                            <div class="flex items-center space-x-2">
                                <div class="status-online" id="mqtt-status"></div>
                                <span class="text-sm text-gray-600 dark:text-gray-300" id="mqtt-status-text">Connecting...</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Flash Messages -->
                @if (session('success'))
                    <div class="glass-card p-4 mb-4 rounded-2xl bg-emerald-500/20 border-emerald-500/50">
                        <p class="text-emerald-800 dark:text-emerald-200">{{ session('success') }}</p>
                    </div>
                @endif

                @if (session('error'))
                    <div class="glass-card p-4 mb-4 rounded-2xl bg-red-500/20 border-red-500/50">
                        <p class="text-red-800 dark:text-red-200">{{ session('error') }}</p>
                    </div>
                @endif

                <!-- Page Content -->
                <main>
                    {{ $slot }}
                </main>
            </div>
        </div>
    </div>

    <!-- Logout Modal -->
    <div x-show="showLogoutModal" 
         class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         style="display: none;">
        <div class="glass-card w-full max-w-md p-6 rounded-3xl border border-white/20 shadow-2xl" @click.away="showLogoutModal = false">
            <div class="flex items-center space-x-4 mb-6">
                <div class="w-14 h-14 rounded-2xl bg-red-100 dark:bg-red-900/30 flex items-center justify-center text-red-600 shadow-inner">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Keluar Sesi</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Apakah Anda yakin ingin keluar dari aplikasi?</p>
                </div>
            </div>

            <div class="flex space-x-3">
                <button @click="showLogoutModal = false" 
                        class="flex-1 px-4 py-3 rounded-xl border border-gray-200 dark:border-white/10 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 font-semibold transition-all">
                    Batal
                </button>
                <form method="POST" action="{{ route('logout') }}" class="flex-1">
                    @csrf
                    <button type="submit" 
                            class="w-full px-4 py-3 rounded-xl bg-gradient-to-r from-red-600 to-red-700 text-white font-bold shadow-lg shadow-red-500/30 hover:shadow-red-500/50 hover:-translate-y-0.5 transform transition-all">
                        Logout
                    </button>
                </form>
            </div>
        </div>
    </div>


    @stack('scripts')
</body>
</html>
