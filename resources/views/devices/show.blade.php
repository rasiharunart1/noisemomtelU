<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-gradient-to-br from-red-600 to-red-800 rounded-lg flex items-center justify-center shadow-lg shadow-red-500/30">
                <!-- Chart Bar Icon -->
                <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
            </div>
            <div>
                <h2 class="text-2xl font-bold bg-gradient-to-r from-red-600 to-red-800 bg-clip-text text-transparent">
                    {{ $device->name ?? $device->device_id }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 font-mono">{{ $device->device_id }}</p>
            </div>
        </div>
    </x-slot>

    <!-- Device Status Card -->
    <div class="glass-card p-6 rounded-2xl mb-8 relative overflow-hidden group">
        <div class="absolute top-0 right-0 w-64 h-64 bg-red-500/10 rounded-full blur-3xl -mr-32 -mt-32 transition-all group-hover:bg-red-500/20"></div>
        
        <div class="flex flex-col md:flex-row items-center justify-between relative z-10">
            <div class="flex items-center space-x-6 mb-4 md:mb-0">
                <div class="relative">
                    <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900 flex items-center justify-center shadow-inner">
                        <svg class="w-10 h-10 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                        </svg>
                    </div>
                    <div class="absolute -bottom-1 -right-1 w-6 h-6 rounded-full border-4 border-white dark:border-gray-900 flex items-center justify-center {{ $device->status === 'online' ? 'bg-emerald-500 animate-pulse' : 'bg-gray-400' }}" id="status-indicator">
                    </div>
                </div>
                
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">Device Status</h3>
                    <div class="flex items-center space-x-2 mb-2">
                        <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $device->status === 'online' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20' : 'bg-gray-500/10 text-gray-600 dark:text-gray-400 border border-gray-500/20' }}" id="status-text">
                            {{ ucfirst($device->status) }}
                        </span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            Last seen: <span id="last-seen">{{ $device->last_seen ? $device->last_seen->diffForHumans() : 'Never' }}</span>
                        </span>
                    </div>

                    <!-- Device Token -->
                    <div class="flex items-center space-x-2" x-data="{ show: false, copied: false }">
                        <div class="text-xs font-mono bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 max-w-[150px] truncate cursor-pointer"
                             @click="show = !show" 
                             title="Click to toggle visibility">
                            <span x-show="!show">••••••••••••••••</span>
                            <span x-show="show">{{ $device->token }}</span>
                        </div>
                        <button class="text-gray-500 hover:text-red-500 transition-colors"
                                @click="navigator.clipboard.writeText('{{ $device->token }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                title="Copy Token">
                            <svg x-show="!copied" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                            <svg x-show="copied" class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                </div>
                
                <!-- Digital Gain Control -->
                <div class="mt-4 p-4 rounded-xl bg-gradient-to-br from-white/5 to-white/10 border border-white/10">
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Digital Gain (Sensitivity)</label>
                        <span class="text-lg font-black text-red-600 dark:text-red-400" id="gain-value">{{ number_format($device->gain, 1) }}x</span>
                    </div>
                    <input type="range" id="gain-slider" min="1" max="25" step="0.5" 
                           value="{{ $device->gain }}"
                           class="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-lg appearance-none cursor-pointer accent-red-600">
                    <div class="flex justify-between mt-1">
                        <span class="text-[10px] text-gray-500">1x</span>
                        <span class="text-[10px] text-gray-500 italic">Adjust sensitivity remotely</span>
                        <span class="text-[10px] text-gray-500">25x</span>
                    </div>
                </div>

                <!-- Threshold Setting -->
                <div class="mt-4 p-4 rounded-xl bg-gradient-to-br from-yellow-500/10 to-orange-500/10 border border-yellow-500/20">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center space-x-2">
                            <svg class="w-4 h-4 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <label class="text-xs font-bold text-yellow-600 dark:text-yellow-500 uppercase tracking-wider">Noise Threshold</label>
                        </div>
                        <span class="text-lg font-black text-yellow-600 dark:text-yellow-500" id="threshold-value">{{ number_format($device->max_db_spl_threshold, 1) }} dB</span>
                    </div>
                    <input type="range" id="threshold-slider" min="30" max="120" step="0.5" 
                           value="{{ $device->max_db_spl_threshold }}"
                           class="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-lg appearance-none cursor-pointer accent-yellow-600">
                    <div class="flex justify-between mt-1">
                        <span class="text-[10px] text-gray-500">30 dB</span>
                        <span class="text-[10px] text-yellow-600 italic">OSHA: 85 dB</span>
                        <span class="text-[10px] text-gray-500">120 dB</span>
                    </div>
                </div>
            </div>

            <div class="flex space-x-3">
                <a href="{{ route('devices.edit', $device) }}" class="btn-secondary flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Edit
                </a>
                <form action="{{ route('devices.destroy', $device) }}" method="POST" onsubmit="return confirm('Delete this device?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="px-4 py-3 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition-colors font-semibold shadow-sm">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Location Card -->
    <!-- Location Card -->
    @if($device->latitude && $device->longitude)
    <div class="glass-card mb-8 rounded-2xl overflow-hidden relative">
        <div class="p-6 border-b border-gray-100 dark:border-white/5 z-10 relative">
             <h3 class="text-lg font-bold text-gray-900 dark:text-white">Device Location</h3>
        </div>
        <div id="map" class="w-full h-80 z-0"></div>
    </div>
    @endif

    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <style>
            #map { z-index: 0; background: transparent; }
            .leaflet-container { background: transparent !important; }
            
            /* Custom Marker Style */
            .custom-marker {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .marker-pin {
                width: 16px;
                height: 16px;
                border-radius: 50%;
                border: 2px solid white;
                box-shadow: 0 0 10px rgba(0,0,0,0.3);
                position: relative;
                z-index: 2;
            }
            .marker-pulse {
                position: absolute;
                width: 32px;
                height: 32px;
                border-radius: 50%;
                opacity: 0.6;
                animation: pulse 2s infinite;
                z-index: 1;
            }
            @keyframes pulse {
                0% { transform: scale(0.5); opacity: 0.8; }
                100% { transform: scale(2.5); opacity: 0; }
            }

            /* Glassmorphism Popup */
            .leaflet-popup-content-wrapper {
                background: rgba(255, 255, 255, 0.8) !important;
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255, 255, 255, 0.2);
                border-radius: 1rem !important;
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1) !important;
            }
            .dark .leaflet-popup-content-wrapper {
                background: rgba(17, 24, 39, 0.8) !important;
                border: 1px solid rgba(255, 255, 255, 0.1);
                color: white !important;
            }
            .leaflet-popup-tip {
                background: rgba(255, 255, 255, 0.8) !important;
                backdrop-filter: blur(10px);
            }
            .dark .leaflet-popup-tip {
                background: rgba(17, 24, 39, 0.8) !important;
            }
        </style>
    @endpush

    @push('scripts')
    @if($device->latitude && $device->longitude)
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var lat = {{ $device->latitude }};
                var lng = {{ $device->longitude }};
                var map = L.map('map').setView([lat, lng], 15);
                
                // Fix map size on load
                setTimeout(function(){ map.invalidateSize()}, 400);
                
                // Tiles: Use CartoDB Positron for a cleaner look
                const isDark = document.documentElement.classList.contains('dark');
                const tileUrl = isDark 
                    ? 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png'
                    : 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png';

                L.tileLayer(tileUrl, {
                    maxZoom: 19,
                    attribution: '&copy; CartoDB'
                }).addTo(map);

                // Custom Icon
                const color = "{{ $device->status === 'online' ? '#fbbf24' : '#ef4444' }}";
                const customIcon = L.divIcon({
                    html: `
                        <div class="custom-marker">
                            <div class="marker-pulse" style="background-color: ${color}"></div>
                            <div class="marker-pin" style="background-color: ${color}"></div>
                        </div>
                    `,
                    className: '',
                    iconSize: [32, 32],
                    iconAnchor: [16, 16]
                });

                L.marker([lat, lng], { icon: customIcon }).addTo(map)
                    .bindPopup(`
                        <div class="p-1 min-w-[120px]">
                            <h3 class="font-bold text-gray-900 dark:text-white text-sm mb-1">{{ $device->name }}</h3>
                            <p class="text-[10px] text-gray-500 font-mono italic">Lat: ${lat.toFixed(4)}, Lng: ${lng.toFixed(4)}</p>
                        </div>
                    `, { closeButton: false })
                    .openPopup();
            });
        </script>
    @endif
    @endpush

    <!-- Charts Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- dB SPL Chart -->
        <div class="glass-card p-6 rounded-2xl border-t-4 border-red-500 lg:col-span-2">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center">
                    <span class="w-2 h-2 rounded-full bg-red-500 mr-2"></span>
                    Noise Level (dB SPL) History
                </h3>
            </div>
            <div class="h-80">
                <canvas id="dbSplChart"></canvas>
            </div>
        </div>

        <!-- RMS Chart -->
        <div class="glass-card p-6 rounded-2xl border-t-4 border-indigo-600">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center">
                    <span class="w-2 h-2 rounded-full bg-indigo-600 mr-2"></span>
                    RMS History
                </h3>
            </div>
            <div class="h-64">
                <canvas id="rmsChart"></canvas>
            </div>
        </div>

                <!-- Big Numbers -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-red-500/10 dark:bg-red-500/20 rounded-2xl p-4 border border-red-500/20 text-center">
                        <p class="text-[10px] font-bold text-red-600 dark:text-red-400 tracking-widest uppercase mb-1">Noise Level</p>
                        <div class="flex items-baseline justify-center space-x-1">
                            <span class="text-3xl font-black text-gray-900 dark:text-white" id="current-db-spl">-</span>
                            <span class="text-xs font-bold text-gray-500">dB</span>
                        </div>
                    </div>
                    <div class="bg-white/5 rounded-2xl p-4 border border-white/10 text-center">
                        <p class="text-[10px] font-bold text-gray-500 tracking-widest uppercase mb-1">RMS</p>
                        <span class="text-2xl font-bold text-gray-900 dark:text-white" id="current-rms">0.0000</span>
                    </div>
                    <div class="bg-white/5 rounded-2xl p-4 border border-white/10 text-center">
                        <p class="text-[10px] font-bold text-gray-500 tracking-widest uppercase mb-1">Peak Freq</p>
                        <div class="flex items-baseline justify-center space-x-1">
                            <span class="text-2xl font-bold text-gray-900 dark:text-white" id="current-freq">0</span>
                            <span class="text-xs font-bold text-gray-500">Hz</span>
                        </div>
                    </div>
                    <div class="bg-white/5 rounded-2xl p-4 border border-white/10 text-center relative group">
                        <div class="absolute -top-2 -right-1 bg-red-600 text-white text-[8px] font-black px-1.5 py-0.5 rounded-full shadow-lg transform group-hover:scale-110 transition-transform">REMOTE</div>
                        <p class="text-[10px] font-bold text-gray-500 tracking-widest uppercase mb-1">Digital Gain</p>
                        <span class="text-2xl font-black text-red-600" id="gain-value">{{ number_format($device->gain, 1) }}x</span>
                    </div>
                </div>
        <!-- Frequency Chart -->
        <div class="glass-card p-6 rounded-2xl border-t-4 border-yellow-500">
             <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center">
                    <span class="w-2 h-2 rounded-full bg-yellow-500 mr-2"></span>
                    Peak Frequency Trends
                </h3>
            </div>
            <div class="h-64">
                <canvas id="frequencyChart"></canvas>
            </div>
        </div>

        <!-- Energy Chart -->
        <div class="glass-card p-6 rounded-2xl border-t-4 border-red-800">
             <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center">
                    <span class="w-2 h-2 rounded-full bg-red-800 mr-2"></span>
                    Total Energy
                </h3>
                <span class="text-2xl font-bold text-red-800 dark:text-red-300" id="current-energy">0</span>
            </div>
            <div class="h-64">
                <canvas id="energyChart"></canvas>
            </div>
        </div>

        <!-- Band Chart -->
        <div class="glass-card p-6 rounded-2xl border-t-4 border-orange-500">
             <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center">
                    <span class="w-2 h-2 rounded-full bg-orange-500 mr-2"></span>
                    Band Distribution
                </h3>
            </div>
            <div class="h-64">
                <canvas id="bandChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Recording Control Section -->
    <div class="glass-card p-6 rounded-2xl mb-8 relative overflow-hidden" x-data="{ recording: false }">
        <div class="absolute top-0 left-0 w-64 h-64 bg-red-500/10 rounded-full blur-3xl -ml-32 -mt-32 transition-all" :class="recording && 'animate-pulse'"></div>
        
        <div class="relative z-10">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-red-500 to-red-700 flex items-center justify-center shadow-lg shadow-red-500/30">
                        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Recording Control</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Remote audio recording from ESP32</p>
                    </div>
                </div>
                
                <div class="flex items-center space-x-2">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400" id="recording-status-text">Idle</span>
                    <div class="w-3 h-3 rounded-full bg-gray-400" id="recording-status-dot"></div>
                </div>
            </div>
            
            <div class="flex space-x-3">
                <button id="start-recording-btn" 
                        {{ $device->status !== 'online' ? 'disabled' : '' }}
                        class="flex-1 px-6 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white rounded-xl font-semibold shadow-lg shadow-emerald-500/30 hover:shadow-emerald-500/50 transition-all hover:scale-105 flex items-center justify-center space-x-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100"
                        title="{{ $device->status !== 'online' ? 'Device must be online to record' : 'Start recording audio' }}">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Start Recording</span>
                </button>
                
                <button id="stop-recording-btn" 
                        disabled
                        class="flex-1 px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-xl font-semibold shadow-lg shadow-red-500/30 hover:shadow-red-500/50 transition-all hover:scale-105 flex items-center justify-center space-x-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100"
                        title="Stop recording">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z" />
                    </svg>
                    <span>Stop Recording</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Scheduled Recordings -->
    <div class="glass-card p-6 rounded-2xl mb-8 relative overflow-hidden" x-data="{ scheduleModalOpen: false }">
        <div class="absolute top-0 right-0 w-64 h-64 bg-emerald-500/5 rounded-full blur-3xl -mr-32 -mt-32"></div>

        <div class="relative z-10">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-600 to-emerald-800 flex items-center justify-center shadow-lg shadow-emerald-500/30">
                        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Scheduled Recordings</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Automate recurring recordings</p>
                    </div>
                </div>
                <button @click="scheduleModalOpen = true" class="px-4 py-2 bg-emerald-600 text-white rounded-lg font-bold text-xs hover:bg-emerald-700 transition-colors shadow-lg shadow-emerald-500/20 flex items-center">
                    <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    New Schedule
                </button>
            </div>

            <!-- Schedules List -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-white/5 dark:text-gray-300">
                        <tr>
                            <th scope="col" class="px-6 py-3 rounded-l-lg">Time Window</th>
                            <th scope="col" class="px-6 py-3">Interval</th>
                            <th scope="col" class="px-6 py-3">Duration</th>
                            <th scope="col" class="px-6 py-3">Status</th>
                            <th scope="col" class="px-6 py-3 rounded-r-lg text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($schedules as $schedule)
                        <tr class="bg-white dark:bg-transparent border-b dark:border-white/5 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                            <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                <div>{{ $schedule->start_time->format('M d, H:i') }}</div>
                                <div class="text-xs text-gray-500">to {{ $schedule->end_time->format('M d, H:i') }}</div>
                            </td>
                            <td class="px-6 py-4">
                                Every {{ $schedule->interval_minutes }} mins
                            </td>
                            <td class="px-6 py-4">
                                {{ $schedule->duration_seconds }} sec
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                    {{ $schedule->status === 'active' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400' : '' }}
                                    {{ $schedule->status === 'pending' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' : '' }}
                                    {{ $schedule->status === 'completed' ? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' : '' }}
                                ">
                                    {{ ucfirst($schedule->status) }}
                                </span>
                                @if($schedule->last_run_at)
                                <div class="text-[10px] mt-1 text-gray-500">Last: {{ $schedule->last_run_at->diffForHumans() }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <form action="{{ route('schedules.destroy', $schedule) }}" method="POST" onsubmit="return confirm('Cancel this schedule?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700 font-medium">Cancel</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-gray-500 italic">No schedules active</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- New Schedule Modal -->
        <template x-teleport="body">
            <div x-show="scheduleModalOpen" 
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 transform scale-95"
                 x-transition:enter-end="opacity-100 transform scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 transform scale-100"
                 x-transition:leave-end="opacity-0 transform scale-95"
                 class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-md p-4">
                
                <div @click.away="scheduleModalOpen = false" class="bg-white dark:bg-gray-900 w-full max-w-lg rounded-[2rem] shadow-2xl border border-gray-200 dark:border-white/10 p-8 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-emerald-500/10 rounded-full blur-2xl -mr-16 -mt-16"></div>
                    
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-6 relative z-10">Create Schedule</h3>
                    
                    <form action="{{ route('devices.schedules.store', $device) }}" method="POST" class="space-y-4 relative z-10">
                        @csrf
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Start Time</label>
                                <input type="datetime-local" name="start_time" required
                                       class="w-full px-4 py-3 bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-xl focus:ring-2 focus:ring-emerald-500 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">End Time</label>
                                <input type="datetime-local" name="end_time" required
                                       class="w-full px-4 py-3 bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-xl focus:ring-2 focus:ring-emerald-500 dark:text-white">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Interval (Minutes)</label>
                                <input type="number" name="interval_minutes" min="1" value="30" required
                                       class="w-full px-4 py-3 bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-xl focus:ring-2 focus:ring-emerald-500 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Duration (Seconds)</label>
                                <input type="number" name="duration_seconds" min="5" max="300" value="30" required
                                       class="w-full px-4 py-3 bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-xl focus:ring-2 focus:ring-emerald-500 dark:text-white">
                            </div>
                        </div>

                        <div class="pt-4 flex space-x-3">
                            <button type="button" @click="scheduleModalOpen = false" class="flex-1 px-4 py-3 bg-gray-100 dark:bg-white/5 text-gray-700 dark:text-gray-300 rounded-xl font-bold hover:bg-gray-200 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" class="flex-1 px-4 py-3 bg-emerald-600 text-white rounded-xl font-bold hover:bg-emerald-700 shadow-lg shadow-emerald-500/30 transition-all hover:scale-[1.02]">
                                Create Schedule
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </div>
    <div class="glass-card p-6 rounded-2xl mb-8 relative overflow-hidden group" x-data="{ wifiModalOpen: false }">
        <div class="absolute top-0 right-0 w-64 h-64 bg-blue-500/5 rounded-full blur-3xl -mr-32 -mt-32 transition-all group-hover:bg-blue-500/10"></div>
        
        <div class="relative z-10">
            <div class="flex items-center justify-between mb-6">
                 <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-600 to-blue-800 flex items-center justify-center shadow-lg shadow-blue-500/30 group-hover:scale-110 transition-transform">
                        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">WiFi Connectivity</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Manage device network credentials remotely</p>
                    </div>
                </div>
                <div class="hidden md:block">
                    <span class="text-[10px] font-bold text-blue-600/50 uppercase tracking-widest bg-blue-500/5 px-2 py-1 rounded-full border border-blue-500/10">Network Control</span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Update WiFi Button -->
                <button @click="wifiModalOpen = true"
                        {{ $device->status !== 'online' ? 'disabled' : '' }}
                        class="px-6 py-4 bg-white/5 dark:bg-white/5 text-gray-900 dark:text-white border border-gray-200 dark:border-white/10 rounded-2xl font-bold hover:bg-blue-500/5 hover:border-blue-500/30 hover:text-blue-600 dark:hover:text-blue-400 transition-all flex items-center justify-center space-x-3 group/btn disabled:opacity-50 disabled:cursor-not-allowed shadow-sm">
                    <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center group-hover/btn:bg-blue-500 group-hover/btn:text-white transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                    </div>
                    <div class="text-left">
                        <span class="block text-sm">Update WiFi</span>
                        <span class="block text-[10px] opacity-60 font-medium italic">Configure new SSID/Pass</span>
                    </div>
                </button>

                <!-- Reset to AP Button -->
                <button onclick="resetWifiToAP()"
                        {{ $device->status !== 'online' ? 'disabled' : '' }}
                        class="px-6 py-4 bg-gradient-to-br from-orange-500 to-red-600 text-white rounded-2xl font-bold shadow-lg shadow-orange-500/20 hover:shadow-orange-500/40 hover:scale-[1.02] active:scale-[0.98] transition-all flex items-center justify-center space-x-3 disabled:opacity-50 disabled:cursor-not-allowed group/reset">
                    <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center group-hover/reset:rotate-12 transition-transform">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div class="text-left">
                        <span class="block text-sm">Reset to AP Mode</span>
                        <span class="block text-[10px] font-medium opacity-80 italic">Enter Provisioning Mode</span>
                    </div>
                </button>
            </div>
        </div>

        <!-- WiFi Update Modal -->
        <template x-teleport="body">
            <div x-show="wifiModalOpen" 
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 transform scale-95"
                 x-transition:enter-end="opacity-100 transform scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 transform scale-100"
                 x-transition:leave-end="opacity-0 transform scale-95"
                 class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-md p-4">
                
                <div @click.away="wifiModalOpen = false" 
                     class="bg-white dark:bg-gray-900 w-full max-w-md rounded-[2.5rem] shadow-2xl border border-gray-200 dark:border-white/10 p-8 overflow-hidden relative"
                     x-data="{ showPass: false }">
                    
                    <!-- Decorative background element -->
                    <div class="absolute -top-24 -right-24 w-48 h-48 bg-blue-500/10 rounded-full blur-3xl"></div>
                    
                    <div class="relative z-10">
                        <div class="flex items-center space-x-4 mb-8">
                            <div class="w-14 h-14 rounded-2xl bg-blue-600 flex items-center justify-center shadow-xl shadow-blue-500/20 text-white">
                                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-2xl font-black text-gray-900 dark:text-white leading-tight">WiFi Config</h3>
                                <p class="text-xs font-bold text-gray-500 uppercase tracking-widest">Update Device Credentials</p>
                            </div>
                        </div>
                        
                        <div class="space-y-6">
                            <div class="group">
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 px-1">Network SSID</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                        </svg>
                                    </div>
                                    <input type="text" id="wifi-ssid" 
                                           class="w-full pl-11 pr-4 py-4 rounded-2xl border-none bg-gray-100 dark:bg-white/5 text-gray-900 dark:text-white font-bold text-sm focus:ring-2 focus:ring-blue-500 transition-all placeholder-gray-400 dark:placeholder-gray-600 shadow-inner" 
                                           placeholder="Network name">
                                </div>
                            </div>
                            
                            <div class="group">
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 px-1">Network Password</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                    </div>
                                    <input :type="showPass ? 'text' : 'password'" id="wifi-password" 
                                           class="w-full pl-11 pr-12 py-4 rounded-2xl border-none bg-gray-100 dark:bg-white/5 text-gray-900 dark:text-white font-bold text-sm focus:ring-2 focus:ring-blue-500 transition-all placeholder-gray-400 dark:placeholder-gray-600 shadow-inner" 
                                           placeholder="••••••••">
                                    <button @click="showPass = !showPass" class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-blue-500 transition-colors">
                                        <svg x-show="!showPass" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        <svg x-show="showPass" style="display-none" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.882 9.882L5.146 5.147m13.71 13.71l-4.738-4.736m-4.568-4.568L18.854 2.146" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="group">
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 px-1">Server Host IP</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                                            </svg>
                                        </div>
                                        <input type="text" id="server-ip" 
                                               class="w-full pl-11 pr-4 py-4 rounded-2xl border-none bg-gray-100 dark:bg-white/5 text-gray-900 dark:text-white font-bold text-sm focus:ring-2 focus:ring-blue-500 transition-all placeholder-gray-400 dark:placeholder-gray-600 shadow-inner" 
                                               placeholder="192.168.x.x">
                                    </div>
                                </div>
                                <div class="group">
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 px-1">Server Port</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m12 4a2 2 0 100-4m0 4a2 2 0 110-4m-6 0v2m0-6V4m6 6v10m-6-2v2m-6-2v2" />
                                            </svg>
                                        </div>
                                        <input type="number" id="server-port" 
                                               class="w-full pl-11 pr-4 py-4 rounded-2xl border-none bg-gray-100 dark:bg-white/5 text-gray-900 dark:text-white font-bold text-sm focus:ring-2 focus:ring-blue-500 transition-all placeholder-gray-400 dark:placeholder-gray-600 shadow-inner" 
                                               placeholder="8000">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 p-4 rounded-2xl bg-amber-500/10 border border-amber-500/20 flex items-start space-x-3">
                            <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <p class="text-[10px] font-bold text-amber-700 dark:text-amber-400 italic leading-relaxed">
                                Warning: Incorrect credentials will cause the device to enter AP mode, requiring local reconnection.
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mt-10">
                            <button @click="wifiModalOpen = false" 
                                    class="px-6 py-4 rounded-2xl font-bold bg-gray-100 dark:bg-white/5 text-gray-500 hover:bg-gray-200 dark:hover:bg-white/10 hover:text-gray-900 dark:hover:text-white transition-all shadow-sm">
                                Cancel
                            </button>
                            <button onclick="updateWifiCredentials()" 
                                    class="px-6 py-4 rounded-2xl font-bold bg-blue-600 text-white hover:bg-blue-700 shadow-lg shadow-blue-500/30 hover:scale-[1.03] active:scale-[0.97] transition-all">
                                Apply Changes
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- Audio Recordings -->
    <div class="glass-card rounded-2xl overflow-hidden mb-8">
        <div class="p-6 border-b border-white/10 bg-gray-50/50 dark:bg-white/5">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold text-lg text-gray-900 dark:text-white flex items-center">
                    <svg class="w-5 h-5 mr-2 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                    </svg>
                    Audio Recordings (SD Card Uploads)
                </h3>
                <span class="text-xs text-gray-500 font-medium">{{ $recordings->count() }} Recordings</span>
            </div>
            
            <!-- Bulk Actions -->
            <div id="bulk-actions" class="hidden space-x-2">
                <button id="download-selected-btn" class="px-4 py-2 bg-red-600 text-white rounded-lg font-semibold hover:bg-red-700 transition-colors text-sm flex items-center space-x-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    <span>Download Selected (<span id="selected-count">0</span>)</span>
                </button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-white/5 text-gray-500 dark:text-gray-400 text-xs uppercase">
                    <tr>
                        <th class="px-6 py-4 font-semibold text-left w-12">
                            <input type="checkbox" id="select-all" class="w-4 h-4 rounded border-gray-300 text-red-600 focus:ring-red-500">
                        </th>
                        <th class="px-6 py-4 font-semibold text-left">Time Uploaded</th>
                        <th class="px-6 py-4 font-semibold text-left">Filename</th>
                        <th class="px-6 py-4 font-semibold text-right">Size</th>
                        <th class="px-6 py-4 font-semibold text-center">Listen</th>
                        <th class="px-6 py-4 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @forelse($recordings as $rec)
                        <tr class="hover:bg-red-50/50 dark:hover:bg-red-900/10 transition-colors">
                            <td class="px-6 py-4">
                                <input type="checkbox" class="recording-checkbox w-4 h-4 rounded border-gray-300 text-red-600 focus:ring-red-500" value="{{ $rec->id }}">
                            </td>
                            <td class="px-6 py-4 text-gray-900 dark:text-white whitespace-nowrap">
                                {{ $rec->created_at->format('M d, H:i:s') }}
                            </td>
                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400 font-mono text-xs">
                                {{ $rec->filename }}
                            </td>
                            <td class="px-6 py-4 text-right text-gray-700 dark:text-gray-300">
                                {{ number_format($rec->file_size_bytes / 1024, 1) }} KB
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex justify-center">
                                    <audio controls class="h-8 max-w-[200px] rounded-lg">
                                        <source src="{{ asset('storage/' . $rec->path) }}" type="audio/wav">
                                        Your browser does not support the audio element.
                                    </audio>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('recordings.download', $rec) }}" class="text-red-600 hover:text-red-500 font-medium text-xs">Download</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-500 italic">No recordings uploaded yet</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Logs REMOVED -->

    @push('scripts')
    <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        // --- Telkom Theme Colors ---
        const colors = {
            primary: '#E31E24',      // Telkom Red
            primaryDark: '#8B0000',  // Dark Red
            secondary: '#d4d4d8',    // Gray-300
            grid: 'rgba(255, 255, 255, 0.05)',
            text: '#9ca3af'
        };

        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: colors.grid }, ticks: { color: colors.text } },
                y: { grid: { color: colors.grid }, ticks: { color: colors.text } }
            },
            animation: { duration: 0 } // Disable animation for realtime performance
        };

        // --- Charts Initialization ---
        
        // 0. dB SPL Chart (Premium Red)
        const dbSplChart = new Chart(document.getElementById('dbSplChart'), {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    data: [],
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    borderWidth: 3,
                    pointRadius: 2,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                ...commonOptions,
                scales: {
                    ...commonOptions.scales,
                    y: { ...commonOptions.scales.y, beginAtZero: false, suggestedMin: 30, suggestedMax: 100 }
                }
            }
        });

        // 1. RMS Chart (Indigo)
        const rmsChart = new Chart(document.getElementById('rmsChart'), {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    data: [],
                    borderColor: colors.primary,
                    backgroundColor: 'rgba(227, 30, 36, 0.1)',
                    borderWidth: 2,
                    pointRadius: 0,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                ...commonOptions,
                scales: {
                    ...commonOptions.scales,
                    y: { ...commonOptions.scales.y, beginAtZero: true, suggestedMax: 0.5 }
                }
            }
        });

        // 2. Frequency Chart (Dark/White)
        const frequencyChart = new Chart(document.getElementById('frequencyChart'), {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    data: [],
                    borderColor: '#9ca3af',
                    backgroundColor: 'rgba(156, 163, 175, 0.1)',
                    borderWidth: 2,
                    pointRadius: 0,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: commonOptions
        });

        // 3. Energy Chart (Dark Red)
        const energyChart = new Chart(document.getElementById('energyChart'), {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    data: [],
                    borderColor: colors.primaryDark,
                    backgroundColor: 'rgba(139, 0, 0, 0.1)',
                    borderWidth: 2,
                    pointRadius: 0,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: commonOptions
        });

        // 4. Band Chart (Bar)
        const bandChart = new Chart(document.getElementById('bandChart'), {
            type: 'bar',
            data: {
                labels: ['Low', 'Mid', 'High'],
                datasets: [{
                    data: [0, 0, 0],
                    backgroundColor: [colors.primaryDark, colors.primary, '#ef4444'],
                    borderRadius: 8,
                    barThickness: 40
                }]
            },
            options: {
                ...commonOptions,
                animation: { duration: 500 }
            }
        });

        // --- Load Initial Data ---
        @if($recentLogs->count() > 0)
            const initialLogs = @json($recentLogs->reverse()->values());
            initialLogs.forEach(log => {
                const time = new Date(log.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                
                // Add data to charts
                [dbSplChart, rmsChart, frequencyChart, energyChart].forEach(chart => {
                    chart.data.labels.push(time);
                    if (chart.data.labels.length > 50) chart.data.labels.shift();
                });

                dbSplChart.data.datasets[0].data.push(log.db_spl || 0);
                rmsChart.data.datasets[0].data.push(log.rms);
                frequencyChart.data.datasets[0].data.push(log.peak_frequency);
                energyChart.data.datasets[0].data.push(log.total_energy);

                [dbSplChart, rmsChart, frequencyChart, energyChart].forEach(chart => {
                    if (chart.data.datasets[0].data.length > 50) chart.data.datasets[0].data.shift();
                });
            });

            // Update Band Chart
            const latest = initialLogs[initialLogs.length - 1];
            bandChart.data.datasets[0].data = [latest.band_low, latest.band_mid, latest.band_high];

            // Update All
            [dbSplChart, rmsChart, frequencyChart, energyChart, bandChart].forEach(chart => chart.update());
        @endif

        // --- MQTT Realtime Update ---
        
        // Update credentials from backend
        const mqttHost = "{{ config('mqtt.host', env('MQTT_HOST')) }}";
        const mqttPort = "8884"; // WebSocket Port
        const mqttUser = "{{ config('mqtt.username', env('MQTT_USERNAME')) }}"; 
        const mqttPass = "{{ config('mqtt.password', env('MQTT_PASSWORD')) }}";
        const topic = "{{ $device->mqtt_topic }}";

        console.log(`Connecting to wss://${mqttHost}:${mqttPort}/mqtt...`);

        const client = mqtt.connect(`wss://${mqttHost}:${mqttPort}/mqtt`, {
            username: mqttUser,
            password: mqttPass,
            clientId: 'noisemon_detail_' + Math.random().toString(16).substr(2, 8)
        });

        client.on('connect', () => {
            console.log('Connected to MQTT!');
            client.subscribe(topic);
            console.log('Subscribed to:', topic);

            // Subscribe to status topic
            const statusTopic = topic.replace('/data', '/status');
            client.subscribe(statusTopic);
            console.log('Subscribed to:', statusTopic);
        });

        client.on('message', (topic, message) => {
            try {
                const data = JSON.parse(message.toString());
                const now = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });

                // Handle Status Update
                if (data.status) {
                    const indicator = document.getElementById('status-indicator');
                    const text = document.getElementById('status-text');
                    
                    if (data.status === 'online') {
                        indicator.className = "absolute -bottom-1 -right-1 w-6 h-6 rounded-full border-4 border-white dark:border-gray-900 flex items-center justify-center bg-emerald-500 animate-pulse";
                        text.className = "px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20";
                        text.innerHTML = "Online";
                    } else {
                        indicator.className = "absolute -bottom-1 -right-1 w-6 h-6 rounded-full border-4 border-white dark:border-gray-900 flex items-center justify-center bg-gray-400";
                        text.className = "px-3 py-1 rounded-full text-xs font-semibold bg-gray-500/10 text-gray-600 dark:text-gray-400 border border-gray-500/20";
                        text.innerHTML = "Offline";
                    }
                    // return; // REMOVED: Allow processing other data even if status is present
                }

                // Update Big Numbers (Safety Checks Added)
                if (data.audio) {
                    if (data.audio.rms !== undefined && data.audio.rms !== null) {
                        document.getElementById('current-rms').innerText = Number(data.audio.rms).toFixed(4);
                    }
                    if (data.audio.db_spl !== undefined && data.audio.db_spl !== null) {
                        document.getElementById('current-db-spl').innerText = Number(data.audio.db_spl).toFixed(1);
                    }
                }
                if (data.fft) {
                    if (data.fft.peak_frequency !== undefined && data.fft.peak_frequency !== null) {
                        document.getElementById('current-freq').innerText = Number(data.fft.peak_frequency).toFixed(0);
                    }
                }
                document.getElementById('last-seen').innerText = "Just now";

                // Update Charts only if data exists
                if (data.audio && data.fft) {
                    [dbSplChart, rmsChart, frequencyChart, energyChart].forEach(chart => {
                        chart.data.labels.push(now);
                        if (chart.data.labels.length > 50) chart.data.labels.shift();
                    });

                    // Push new data
                    dbSplChart.data.datasets[0].data.push(data.audio.db_spl || 0);
                    rmsChart.data.datasets[0].data.push(data.audio.rms);
                    frequencyChart.data.datasets[0].data.push(data.fft.peak_frequency);
                    energyChart.data.datasets[0].data.push(data.fft.total_energy);

                    // Start shifting old data
                    [dbSplChart, rmsChart, frequencyChart, energyChart].forEach(chart => {
                        if (chart.data.datasets[0].data.length > 50) chart.data.datasets[0].data.shift();
                        chart.update();
                    });

                    // Update Band Chart
                    bandChart.data.datasets[0].data = [
                        data.fft.band_energy.low,
                        data.fft.band_energy.mid,
                        data.fft.band_energy.high
                    ];
                    bandChart.update();

                }

            } catch (e) {
                console.error("Error parsing MQTT message:", e);
            }
        });

        // --- Digital Gain Logic ---
        const gainSlider = document.getElementById('gain-slider');
        const gainValueDisp = document.getElementById('gain-value');
        let gainTimeout = null;

        gainSlider.addEventListener('input', (e) => {
            const val = parseFloat(e.target.value).toFixed(1);
            gainValueDisp.innerText = val + 'x';
            
            // Clear previous timeout to debounce API call
            clearTimeout(gainTimeout);
            
            // 1. Instant MQTT Command (Fast Response)
            const controlTopic = topic.replace('/data', '/control');
            const payload = JSON.stringify({ action: 'set_gain', value: parseFloat(val) });
            client.publish(controlTopic, payload);
            console.log(`MQTT: Setting gain to ${val} on ${controlTopic}`);

            // 2. Debounced API call to persist in DB
            gainTimeout = setTimeout(async () => {
                try {
                    const response = await fetch("{{ route('devices.gain', $device) }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ gain: val })
                    });
                    const result = await response.json();
                    if(result.success) console.log('Database: Gain persisted');
                } catch (err) {
                    console.error('Failed to persist gain:', err);
                }
            }, 1000);
        });

        // --- Threshold Slider Logic ---
        const thresholdSlider = document.getElementById('threshold-slider');
        const thresholdValueDisp = document.getElementById('threshold-value');
        let thresholdTimeout = null;

        thresholdSlider.addEventListener('input', (e) => {
            const val = parseFloat(e.target.value).toFixed(1);
            thresholdValueDisp.innerText = val + ' dB';
            
            // Clear previous timeout
            clearTimeout(thresholdTimeout);
            
            // Debounced API call to persist in DB
            thresholdTimeout = setTimeout(async () => {
                try {
                    const response = await fetch("{{ route('devices.threshold', $device) }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ threshold: val })
                    });
                    const result = await response.json();
                    if(result.success) console.log('Threshold updated:', val);
                } catch (err) {
                    console.error('Failed to update threshold:', err);
                }
            }, 1000);
        });

        // --- Recording Control Logic ---
        const startRecordingBtn = document.getElementById('start-recording-btn');
        const stopRecordingBtn = document.getElementById('stop-recording-btn');
        const recordingStatusText = document.getElementById('recording-status-text');
        const recordingStatusDot = document.getElementById('recording-status-dot');

        // Subscribe to recording status topic
        const recordingStatusTopic = 'audio/{{ $device->device_id }}/recording_status';
        client.subscribe(recordingStatusTopic);
        console.log('Subscribed to:', recordingStatusTopic);

        // Handle recording status updates via MQTT
        client.on('message', (topic, message) => {
            if (topic === recordingStatusTopic) {
                try {
                    const data = JSON.parse(message.toString());
                    updateRecordingStatus(data.status);
                } catch (e) {
                    console.error('Error parsing recording status:', e);
                }
            }
        });

        function updateRecordingStatus(status) {
            if (status === 'recording' || status === 'uploading') {
                if(status === 'recording') {
                     recordingStatusText.innerText = 'Recording...';
                     recordingStatusDot.className = 'w-3 h-3 rounded-full bg-red-500 animate-pulse';
                } else {
                     recordingStatusText.innerText = 'Uploading...';
                     recordingStatusDot.className = 'w-3 h-3 rounded-full bg-blue-500 animate-pulse';
                }
                startRecordingBtn.disabled = true;
                stopRecordingBtn.disabled = (status === 'uploading'); // Disable stop if uploading
            } else {
                recordingStatusText.innerText = 'Idle';
                recordingStatusDot.className = 'w-3 h-3 rounded-full bg-gray-400';
                
                // Only enable start button if device is online
                const deviceOnline = document.getElementById('status-text').innerText.toLowerCase().includes('online');
                startRecordingBtn.disabled = !deviceOnline;
                stopRecordingBtn.disabled = true;
            }
        }

        // Update button states when device status changes
        client.on('message', (topic, message) => {
            try {
                const data = JSON.parse(message.toString());
                
                // Handle status updates
                // Handle status updates
                if (data.status) {
                    const indicator = document.getElementById('status-indicator');
                    const text = document.getElementById('status-text');
                    
                    if (data.status === 'online' || data.status === 'recording') {
                        indicator.className = "absolute -bottom-1 -right-1 w-6 h-6 rounded-full border-4 border-white dark:border-gray-900 flex items-center justify-center bg-emerald-500 animate-pulse";
                        text.className = "px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20";
                        text.innerHTML = data.status === 'recording' ? "Recording" : "Online";
                        
                        // If we are recording, don't mess with buttons here, let updateRecordingStatus handle it
                        // Only if we are JUST online (idle), we ensure start button is ready
                        if (data.status === 'online') {
                             if (stopRecordingBtn.disabled) {
                                 startRecordingBtn.disabled = false;
                                 startRecordingBtn.title = "Start recording audio";
                             }
                        }
                    } else {
                        indicator.className = "absolute -bottom-1 -right-1 w-6 h-6 rounded-full border-4 border-white dark:border-gray-900 flex items-center justify-center bg-gray-400";
                        text.className = "px-3 py-1 rounded-full text-xs font-semibold bg-gray-500/10 text-gray-600 dark:text-gray-400 border border-gray-500/20";
                        text.innerHTML = "Offline";
                        
                        // Disable recording buttons
                        startRecordingBtn.disabled = true;
                        startRecordingBtn.title = "Device must be online to record";
                        stopRecordingBtn.disabled = true;
                    }
                }
            } catch (e) {
                console.error('Error in message handler:', e);
            }
        });

        startRecordingBtn.addEventListener('click', async () => {
            try {
                const response = await fetch("{{ route('devices.record.start', $device) }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
                const result = await response.json();
                if (result.success) {
                    console.log('Recording started');
                    updateRecordingStatus('recording');
                }
            } catch (err) {
                console.error('Failed to start recording:', err);
            }
        });

        stopRecordingBtn.addEventListener('click', async () => {
            try {
                const response = await fetch("{{ route('devices.record.stop', $device) }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
                const result = await response.json();
                if (result.success) {
                    console.log('Recording stopped');
                    updateRecordingStatus('idle');
                }
            } catch (err) {
                console.error('Failed to stop recording:', err);
            }
        });

        // --- Bulk Download Logic ---
        const selectAllCheckbox = document.getElementById('select-all');
        const recordingCheckboxes = document.querySelectorAll('.recording-checkbox');
        const bulkActions = document.getElementById('bulk-actions');
        const selectedCountSpan = document.getElementById('selected-count');
        const downloadSelectedBtn = document.getElementById('download-selected-btn');

        function updateBulkActions() {
            const selectedCheckboxes = document.querySelectorAll('.recording-checkbox:checked');
            const count = selectedCheckboxes.length;
            
            if (count > 0) {
                bulkActions.classList.remove('hidden');
                bulkActions.classList.add('flex');
                selectedCountSpan.innerText = count;
            } else {
                bulkActions.classList.add('hidden');
                bulkActions.classList.remove('flex');
            }
        }

        selectAllCheckbox.addEventListener('change', (e) => {
            recordingCheckboxes.forEach(checkbox => {
                checkbox.checked = e.target.checked;
            });
            updateBulkActions();
        });

        recordingCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                updateBulkActions();
                
                // Update select-all checkbox state
                const allChecked = Array.from(recordingCheckboxes).every(cb => cb.checked);
                selectAllCheckbox.checked = allChecked;
            });
        });

        downloadSelectedBtn.addEventListener('click', async () => {
            const selectedIds = Array.from(document.querySelectorAll('.recording-checkbox:checked'))
                .map(cb => cb.value);
            
            if (selectedIds.length === 0) return;

            // Create a form and submit it
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = "{{ route('recordings.bulk-download') }}";
            
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = '{{ csrf_token() }}';
            form.appendChild(csrfInput);
            
            selectedIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'recording_ids[]';
                input.value = id;
                form.appendChild(input);
            });
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        });

        // --- WiFi Management Logic ---
        async function resetWifiToAP() {
            if (!confirm('WARNING: This will clear WiFi credentials on the device.\nThe device will restart and create a temporary "Noisemon_Setup" Hotspot.\nYou need to reconnect to it to configure new WiFi.\n\nContinue?')) return;

            try {
                const response = await fetch("{{ route('devices.wifi.reset', $device) }}", {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                });
                const result = await response.json();
                if(result.success) {
                       alert('Command sent! Device is resetting to AP mode...');
                       location.reload();
                } else alert('Failed: ' + result.message);
            } catch (err) {
                alert('Error sending command');
                console.error(err);
            }
        }

        async function updateWifiCredentials() {
             const ssid = document.getElementById('wifi-ssid').value;
             const password = document.getElementById('wifi-password').value;
             const server_ip = document.getElementById('server-ip').value;
             const server_port = document.getElementById('server-port').value;
             
             if(!ssid || !password || !server_ip || !server_port) {
                 alert('Please fill in all fields (SSID, Password, Server IP, and Port)');
                 return;
             }

             if(!confirm('Device will try to connect to: ' + ssid + '\nAnd upload to: ' + server_ip + ':' + server_port + '\n\nContinue?')) return;

             try {
                const response = await fetch("{{ route('devices.wifi.update', $device) }}", {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ ssid, password, server_ip, server_port })
                });
                const result = await response.json();
                if(result.success) {
                       alert('Command sent! Device is updating WiFi...');
                       location.reload();
                } else alert('Failed: ' + result.message);
            } catch (err) {
                alert('Error sending command');
                console.error(err);
            }
        }
    </script>
    @endpush
</x-app-layout>
