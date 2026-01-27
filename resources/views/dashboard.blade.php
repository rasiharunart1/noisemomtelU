<x-app-layout>
    <x-slot name="header">
        Dashboard
    </x-slot>

    <!-- Stats Overview -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="metric-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Total Devices</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $devices->count() }}</p>
                </div>
                <div class="p-3 bg-purple-500/20 rounded-lg">
                    <svg class="w-8 h-8 text-purple-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M7 2a2 2 0 00-2 2v12a2 2 0 002 2h6a2 2 0 002-2V4a2 2 0 00-2-2H7zm3 14a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="metric-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Online</p>
                    <p class="text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ $devices->where('status', 'online')->count() }}</p>
                </div>
                <div class="p-3 bg-emerald-500/20 rounded-lg">
                    <svg class="w-8 h-8 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="metric-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Offline</p>
                    <p class="text-3xl font-bold text-gray-600 dark:text-gray-400">{{ $devices->where('status', 'offline')->count() }}</p>
                </div>
                <div class="p-3 bg-gray-500/20 rounded-lg">
                    <svg class="w-8 h-8 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="metric-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Exceeding Threshold</p>
                    <p class="text-3xl font-bold text-yellow-600 dark:text-yellow-400">{{ $exceedingCount }}</p>
                </div>
                <div class="p-3 bg-yellow-500/20 rounded-lg">
                    <svg class="w-8 h-8 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="metric-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">MQTT Status</p>
                    <div class="flex items-center space-x-2 mt-2">
                        <div class="status-offline" id="mqtt-status"></div>
                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400" id="mqtt-status-text">Connecting...</span>
                    </div>
                </div>
                <div class="p-3 bg-indigo-500/20 rounded-lg">
                    <svg class="w-8 h-8 text-indigo-500" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M2 5a2 2 0 012-2h7a2 2 0 012 2v4a2 2 0 01-2 2H9l-3 3v-3H4a2 2 0 01-2-2V5z"></path>
                        <path d="M15 7v2a4 4 0 01-4 4H9.828l-1.766 1.767c.28.149.599.233.938.233h2l3 3v-3h2a2 2 0 002-2V9a2 2 0 00-2-2h-1z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Device Map -->
    @if($devicesWithLocation->count() > 0)
    <div class="glass-card rounded-2xl overflow-hidden mb-8">
        <div class="p-6 border-b border-white/10 bg-gray-50/50 dark:bg-white/5">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="font-bold text-lg text-gray-900 dark:text-white flex items-center">
                        <svg class="w-5 h-5 mr-2 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Device Locations
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $devicesWithLocation->count() }} devices with location data</p>
                </div>
                <div class="flex items-center space-x-4 text-xs">
                    <div class="flex items-center space-x-1">
                        <div class="w-3 h-3 rounded-full bg-emerald-500"></div>
                        <span class="text-gray-600 dark:text-gray-400">Online, Normal</span>
                    </div>
                    <div class="flex items-center space-x-1">
                        <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                        <span class="text-gray-600 dark:text-gray-400">Exceeding Threshold</span>
                    </div>
                    <div class="flex items-center space-x-1">
                        <div class="w-3 h-3 rounded-full bg-gray-500"></div>
                        <span class="text-gray-600 dark:text-gray-400">Offline</span>
                    </div>
                </div>
            </div>
        </div>
        <div id="devices-map" class="w-full h-96"></div>
    </div>
    @endif

    <!-- Device Cards Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
        @forelse($devices as $device)
            <div class="glass-card p-6 hover:scale-105 transition-transform duration-300" data-device-id="{{ $device->device_id }}">
                <!-- Device Header -->
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                            {{ $device->name ?? $device->device_id }}
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $device->device_id }}</p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="{{ $device->status === 'online' ? 'status-online' : 'status-offline' }}"></div>
                        <span data-status-text class="text-sm font-medium {{ $device->status === 'online' ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-600 dark:text-gray-400' }}">
                            {{ ucfirst($device->status) }}
                        </span>
                    </div>
                </div>

                <!-- Realtime Data Display -->
                <div class="space-y-4">
                    <!-- Main Noise Metrics -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-red-500/10 dark:bg-red-500/20 rounded-2xl p-4 border border-red-500/20">
                            <p class="text-[10px] font-bold text-red-600 dark:text-red-400 uppercase tracking-widest mb-1">Noise Level</p>
                            <div class="flex items-baseline space-x-1">
                                <span class="text-2xl font-black text-gray-900 dark:text-white" data-metric="db_spl">-</span>
                                <span class="text-xs font-bold text-gray-500">dB</span>
                            </div>
                        </div>
                        <div class="bg-indigo-500/10 dark:bg-indigo-500/20 rounded-2xl p-4 border border-indigo-500/20">
                            <p class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-widest mb-1">Peak Freq</p>
                            <div class="flex items-baseline space-x-1">
                                <span class="text-2xl font-black text-gray-900 dark:text-white" data-metric="peak_frequency">-</span>
                                <span class="text-xs font-bold text-gray-500">Hz</span>
                            </div>
                        </div>
                    </div>

                    <!-- Secondary Metrics -->
                    <div class="grid grid-cols-3 gap-2">
                        <div class="bg-white/5 rounded-xl p-3 border border-white/10">
                            <p class="text-[9px] font-bold text-gray-500 uppercase">RMS</p>
                            <p class="text-sm font-bold text-gray-900 dark:text-white" data-metric="rms">-</p>
                        </div>
                        <div class="bg-white/5 rounded-xl p-3 border border-white/10">
                            <p class="text-[9px] font-bold text-gray-500 uppercase">Energy</p>
                            <p class="text-sm font-bold text-gray-900 dark:text-white" data-metric="total_energy">-</p>
                        </div>
                        <div class="bg-white/5 rounded-xl p-3 border border-white/10">
                            <p class="text-[9px] font-bold text-gray-500 uppercase">Gain</p>
                            <p class="text-sm font-bold text-red-500" id="gain-{{ $device->device_id }}">{{ number_format($device->gain, 1) }}x</p>
                        </div>
                    </div>

                    <!-- Band Energy Bar -->
                    <div class="bg-white/5 rounded-2xl p-4 border border-white/10">
                        <div class="flex justify-between items-center mb-3">
                            <h4 class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">Frequency Bands</h4>
                            <div class="flex space-x-2">
                                <span class="w-2 h-2 rounded-full bg-red-500"></span>
                                <span class="w-2 h-2 rounded-full bg-yellow-500"></span>
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            </div>
                        </div>
                        <div class="flex items-end justify-between h-12 gap-1">
                            <div class="flex-1 bg-red-500/20 rounded-t-md relative group">
                                <div class="absolute bottom-0 w-full bg-red-500 rounded-t-md transition-all duration-300" data-band="low" style="height: 0%"></div>
                            </div>
                            <div class="flex-1 bg-yellow-500/20 rounded-t-md relative">
                                <div class="absolute bottom-0 w-full bg-yellow-500 rounded-t-md transition-all duration-300" data-band="mid" style="height: 0%"></div>
                            </div>
                            <div class="flex-1 bg-emerald-500/20 rounded-t-md relative">
                                <div class="absolute bottom-0 w-full bg-emerald-500 rounded-t-md transition-all duration-300" data-band="high" style="height: 0%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Spectrum Visualization -->
                <div class="mt-4 bg-white/5 rounded-lg p-3">
                    <h4 class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">LIVE SPECTRUM</h4>
                    <canvas id="spectrum-{{ $device->device_id }}" class="w-full h-24" width="300" height="100"></canvas>
                </div>

                <!-- Control Sliders -->
                <div class="mt-4 space-y-3">
                    <!-- Digital Gain Slider -->
                    <div class="p-3 rounded-xl bg-gradient-to-br from-white/5 to-white/10 border border-white/10">
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-[9px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Digital Gain</label>
                            <span class="text-sm font-black text-red-600 dark:text-red-400 gain-value-display">{{ number_format($device->gain, 1) }}x</span>
                        </div>
                        <input type="range" class="gain-slider w-full h-1.5 bg-gray-200 dark:bg-gray-700 rounded-lg appearance-none cursor-pointer accent-red-600" 
                               min="1" max="25" step="0.5" 
                               value="{{ $device->gain }}"
                               data-device-id="{{ $device->device_id }}"
                               data-device-uuid="{{ $device->id }}">
                    </div>

                    <!-- Threshold Slider -->
                    <div class="p-3 rounded-xl bg-gradient-to-br from-yellow-500/10 to-orange-500/10 border border-yellow-500/20">
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-[9px] font-bold text-yellow-600 dark:text-yellow-500 uppercase tracking-wider">Threshold</label>
                            <span class="text-sm font-black text-yellow-600 dark:text-yellow-500 threshold-value-display">{{ number_format($device->max_db_spl_threshold, 1) }} dB</span>
                        </div>
                        <input type="range" class="threshold-slider w-full h-1.5 bg-gray-200 dark:bg-gray-700 rounded-lg appearance-none cursor-pointer accent-yellow-600" 
                               min="30" max="120" step="0.5" 
                               value="{{ $device->max_db_spl_threshold }}"
                               data-device-id="{{ $device->device_id }}"
                               data-device-uuid="{{ $device->id }}">
                    </div>
                </div>

                <!-- Last Update -->
                <div class="mt-4 pt-4 border-t border-white/10 flex items-center justify-between">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        <span data-metric="last_seen">{{ $device->last_seen ? $device->last_seen->diffForHumans() : 'Never' }}</span>
                    </p>
                    
                     <!-- Mini Recording Controls -->
                     <div class="flex space-x-2">
                        <button class="btn-start-rec p-2 bg-emerald-500/10 text-emerald-600 rounded-lg hover:bg-emerald-500 hover:text-white transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                title="Start Recording"
                                data-device-id="{{ $device->device_id }}"
                                data-device-uuid="{{ $device->id }}"
                                {{ $device->status !== 'online' ? 'disabled' : '' }}>
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </button>
                        <button class="btn-stop-rec p-2 bg-red-500/10 text-red-600 rounded-lg hover:bg-red-500 hover:text-white transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                title="Stop Recording"
                                disabled
                                data-device-id="{{ $device->device_id }}"
                                data-device-uuid="{{ $device->id }}">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z" />
                            </svg>
                        </button>
                        <div class="hidden animate-pulse w-2 h-2 rounded-full bg-red-500 recording-indicator"></div>
                     </div>
                </div>

                <!-- View Detail Button -->
                <a href="{{ route('devices.show', $device) }}" class="mt-4 block text-center btn-secondary">
                    View Details
                </a>
            </div>
        @empty
            <div class="col-span-full glass-card p-12 text-center">
                <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                </svg>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">No Devices Found</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-4">Add your first device to start monitoring</p>
                <a href="{{ route('devices.create') }}" class="btn-primary inline-block">
                    Add Device
                </a>
            </div>
        @endforelse
    </div>

    @push('scripts')
    @if($devicesWithLocation->count() > 0)
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize Map
        const devicesData = @json($devicesWithLocation);
        
        // Calculate map center (average of all device locations)
        const avgLat = devicesData.reduce((sum, d) => sum + parseFloat(d.latitude), 0) / devicesData.length;
        const avgLng = devicesData.reduce((sum, d) => sum + parseFloat(d.longitude), 0) / devicesData.length;
        
        const map = L.map('devices-map').setView([avgLat, avgLng], 12);
        
        // Add tile layer
        const isDark = document.documentElement.classList.contains('dark');
        const tileUrl = isDark 
            ? 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png'
            : 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png';
        
        L.tileLayer(tileUrl, {
            maxZoom: 19,
            attribution: '&copy; CartoDB'
        }).addTo(map);
        
        // Store markers for updates
        const markers = {};
        
        // Function to get marker color based on status and threshold
        function getMarkerColor(device) {
            if (device.status !== 'online') {
                return '#6b7280'; // Gray - offline
            }
            if (device.exceeding_threshold) {
                return '#eab308'; // Yellow - exceeding threshold
            }
            return '#10b981'; // Green - online and normal
        }
        
        // Create markers for each device
        devicesData.forEach(device => {
            const color = getMarkerColor(device);
            
            const icon = L.divIcon({
                html: `
                    <div class="relative">
                        <div class="absolute w-8 h-8 rounded-full opacity-30 animate-ping" style="background-color: ${color}"></div>
                        <div class="relative w-6 h-6 rounded-full border-2 border-white shadow-lg" style="background-color: ${color}"></div>
                    </div>
                `,
                className: '',
                iconSize: [32, 32],
                iconAnchor: [16, 16]
            });
            
            const marker = L.marker([device.latitude, device.longitude], { icon })
                .addTo(map)
                .bindPopup(`
                    <div class="p-2 min-w-[200px]">
                        <h3 class="font-bold text-gray-900 dark:text-white text-sm mb-2">${device.name || device.device_id}</h3>
                        <div class="space-y-1 text-xs">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Status:</span>
                                <span class="font-semibold ${device.status === 'online' ? 'text-emerald-600' : 'text-gray-600'}">${device.status}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Current dB SPL:</span>
                                <span class="font-bold ${device.exceeding_threshold ? 'text-yellow-600' : 'text-gray-900'}">${device.latest_db_spl != null ? Number(device.latest_db_spl).toFixed(1) : '-'} dB</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Threshold:</span>
                                <span class="font-semibold text-gray-700">${device.threshold} dB</span>
                            </div>
                            ${device.exceeding_threshold ? '<div class="mt-2 px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-center font-semibold">⚠️ Exceeding Threshold</div>' : ''}
                        </div>
                        <a href="/devices/${device.id}" class="mt-3 block text-center px-3 py-1.5 bg-red-600 text-white rounded-lg text-xs font-semibold hover:bg-red-700 transition-colors">View Details</a>
                    </div>
                `, { closeButton: false });
            
            markers[device.device_id] = { marker, data: device };
        });
        
        // Fix map size after load
        setTimeout(() => map.invalidateSize(), 400);

        // Function to update marker based on device data
        function updateMarker(deviceId, data) {
            if (!markers[deviceId]) return;
            
            const markerObj = markers[deviceId];
            const device = markerObj.data;
            
            // Update device data
            if (data.status) device.status = data.status;
            if (data.audio && data.audio.db_spl !== undefined) {
                device.latest_db_spl = data.audio.db_spl;
                device.exceeding_threshold = data.audio.db_spl >= device.threshold;
            }
            
            // Get new color
            const color = getMarkerColor(device);
            
            // Update marker icon
            const newIcon = L.divIcon({
                html: `
                    <div class="relative">
                        <div class="absolute w-8 h-8 rounded-full opacity-30 animate-ping" style="background-color: ${color}"></div>
                        <div class="relative w-6 h-6 rounded-full border-2 border-white shadow-lg" style="background-color: ${color}"></div>
                    </div>
                `,
                className: '',
                iconSize: [32, 32],
                iconAnchor: [16, 16]
            });
            
            markerObj.marker.setIcon(newIcon);
            
            // Update popup content
            markerObj.marker.setPopupContent(`
                <div class="p-2 min-w-[200px]">
                    <h3 class="font-bold text-gray-900 dark:text-white text-sm mb-2">${device.name || device.device_id}</h3>
                    <div class="space-y-1 text-xs">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Status:</span>
                            <span class="font-semibold ${device.status === 'online' ? 'text-emerald-600' : 'text-gray-600'}">${device.status}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Current dB SPL:</span>
                            <span class="font-bold ${device.exceeding_threshold ? 'text-yellow-600' : 'text-gray-900'}">${device.latest_db_spl != null ? Number(device.latest_db_spl).toFixed(1) : '-'} dB</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Threshold:</span>
                            <span class="font-semibold text-gray-700">${device.threshold} dB</span>
                        </div>
                        ${device.exceeding_threshold ? '<div class="mt-2 px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-center font-semibold">⚠️ Exceeding Threshold</div>' : ''}
                    </div>
                    <a href="/devices/${device.id}" class="mt-3 block text-center px-3 py-1.5 bg-red-600 text-white rounded-lg text-xs font-semibold hover:bg-red-700 transition-colors">View Details</a>
                </div>
            `);
        }
    </script>
    @endif
    
    <script src="https://cdn.jsdelivr.net/npm/mqtt@5.0.0/dist/mqtt.min.js"></script>
    <script>
        // MQTT WebSocket Connection
        const mqttHost = '{{ $mqttHost }}';
        const mqttTopic = '{{ $mqttTopic }}';
        const mqttUser = "{{ config('mqtt.username', env('MQTT_USERNAME')) }}";
        const mqttPass = "{{ config('mqtt.password', env('MQTT_PASSWORD')) }}";
        
        // Use standard WSS port 8884 for HiveMQ Cloud, or 8083/9001 for others
        // Better to verify connection in setting test first
        const mqttPort = "8884"; 

        const client = mqtt.connect(`wss://${mqttHost}:${mqttPort}/mqtt`, {
            clientId: 'dashboard_' + Math.random().toString(16).substr(2, 8),
            clean: true,
            reconnectPeriod: 5000,
            username: mqttUser,
            password: mqttPass
        });

        client.on('connect', () => {
            console.log('MQTT Connected');
            document.getElementById('mqtt-status').className = 'status-online';
            document.getElementById('mqtt-status-text').textContent = 'Connected';
            
            // Subscribe to all device topics
            client.subscribe(mqttTopic, { qos: 1 });
            console.log('Subscribed to:', mqttTopic);

            // Subscribe to status topic (derived from data topic)
            const statusTopic = mqttTopic.replace('/data', '/status');
            client.subscribe(statusTopic, { qos: 1 });
            console.log('Subscribed to:', statusTopic);
        });

        client.on('error', (err) => {
            console.error('MQTT Error:', err);
            document.getElementById('mqtt-status').className = 'status-offline';
            document.getElementById('mqtt-status-text').textContent = 'Disconnected';
        });

        client.on('message', (topic, message) => {
            try {
                const data = JSON.parse(message.toString());
                updateDeviceCard(data);
                
                // Update map marker if exists
                @if($devicesWithLocation->count() > 0)
                if (typeof updateMarker === 'function') {
                    updateMarker(data.device_id, data);
                }
                @endif
                
                // Update statistics counters
                if (data.status) {
                    updateStatistics();
                }
            } catch (e) {
                console.error('Failed to parse MQTT message:', e);
            }
        });

        // Function to update statistics counters
        function updateStatistics() {
            const allCards = document.querySelectorAll('[data-device-id]');
            let onlineCount = 0;
            let offlineCount = 0;
            let exceedingCount = 0;
            
            allCards.forEach(card => {
                const statusTextCallback = card.querySelector('[data-status-text]');
                if (statusTextCallback) {
                    const status = statusTextCallback.textContent.toLowerCase().trim();
                    if (status === 'online') {
                        onlineCount++;
                    } else {
                        offlineCount++;
                    }
                }
                
                // Check if exceeding threshold (you can enhance this logic)
                const dbSplElement = card.querySelector('[data-metric="db_spl"]');
                if (dbSplElement && dbSplElement.textContent !== '-') {
                    const dbSpl = parseFloat(dbSplElement.textContent);
                    // Get threshold from slider if available
                    const thresholdSlider = card.querySelector('.threshold-slider');
                    if (thresholdSlider) {
                        const threshold = parseFloat(thresholdSlider.value);
                        if (dbSpl >= threshold) {
                            exceedingCount++;
                        }
                    }
                }
            });
            
            // Update stat cards
            const statsCards = document.querySelectorAll('.metric-card');
            if (statsCards[1]) { // Online card
                const onlineValue = statsCards[1].querySelector('.text-3xl');
                if (onlineValue) onlineValue.textContent = onlineCount;
            }
            if (statsCards[2]) { // Offline card
                const offlineValue = statsCards[2].querySelector('.text-3xl');
                if (offlineValue) offlineValue.textContent = offlineCount;
            }
            if (statsCards[3]) { // Exceeding threshold card
                const exceedingValue = statsCards[3].querySelector('.text-3xl');
                if (exceedingValue) exceedingValue.textContent = exceedingCount;
            }
        }

        function updateDeviceCard(data) {
            const card = document.querySelector(`[data-device-id="${data.device_id}"]`);
            if (!card) return;

            // Update Status
            if (data.status) {
                const statusIndicator = card.querySelector('.status-online, .status-offline');
                const statusText = card.querySelector('[data-status-text]');
                
                const isOnline = data.status === 'online' || data.status === 'recording';
                const isRecording = data.status === 'recording';

                if (isOnline) {
                    statusIndicator.className = 'status-online';
                    statusText.className = 'text-sm font-medium text-emerald-600 dark:text-emerald-400';
                    statusText.textContent = isRecording ? 'Recording' : 'Online';
                } else {
                    statusIndicator.className = 'status-offline';
                    statusText.className = 'text-sm font-medium text-gray-600 dark:text-gray-400';
                    statusText.textContent = 'Offline';
                }

                // Update Recording Buttons
                updateRecordingState(data.device_id, isRecording, isOnline);
            }

            // Update audio metrics
            if (data.audio) {
                card.querySelector('[data-metric="rms"]').textContent = data.audio.rms.toFixed(4);
                if (data.audio.db_spl) {
                    card.querySelector('[data-metric="db_spl"]').textContent = data.audio.db_spl.toFixed(1);
                }
            }

            // Update FFT metrics
            if (data.fft) {
                card.querySelector('[data-metric="peak_frequency"]').textContent = data.fft.peak_frequency.toFixed(0);
                card.querySelector('[data-metric="total_energy"]').textContent = Math.round(data.fft.total_energy);
                
                // Update Band Bars
                if (data.fft.band_energy) {
                    const total = data.fft.band_energy.low + data.fft.band_energy.mid + data.fft.band_energy.high || 1;
                    card.querySelector('[data-band="low"]').style.height = (data.fft.band_energy.low / total * 100) + '%';
                    card.querySelector('[data-band="mid"]').style.height = (data.fft.band_energy.mid / total * 100) + '%';
                    card.querySelector('[data-band="high"]').style.height = (data.fft.band_energy.high / total * 100) + '%';
                }
            }

            // Draw Spectrum
            if (data.fft && data.fft.spectrum) {
                const canvas = document.getElementById(`spectrum-${data.device_id}`);
                if (canvas) {
                    const ctx = canvas.getContext('2d');
                    const width = canvas.width;
                    const height = canvas.height;
                    const barWidth = width / data.fft.spectrum.length;
                    
                    // Clear canvas
                    ctx.clearRect(0, 0, width, height);
                    
                    // Create gradient
                    const gradient = ctx.createLinearGradient(0, height, 0, 0);
                    gradient.addColorStop(0, '#7c3aed'); // Purple 600
                    gradient.addColorStop(1, '#ef4444'); // Red 500
                    ctx.fillStyle = gradient;

                    // Draw bars
                    data.fft.spectrum.forEach((value, index) => {
                        const barHeight = (value / 100) * height;
                        const x = index * barWidth;
                        const y = height - barHeight;
                        
                        // Draw rounded top bars
                        ctx.beginPath();
                        ctx.roundRect(x + 1, y, barWidth - 2, barHeight, [2, 2, 0, 0]);
                        ctx.fill();
                    });
                }
            }

            // Update last seen
            card.querySelector('[data-metric="last_seen"]').textContent = 'Just now';
            
            // Add flash effect
            card.classList.add('animate-pulse');
            setTimeout(() => card.classList.remove('animate-pulse'), 500);
        }

        // --- Gain Slider Logic ---
        document.querySelectorAll('.gain-slider').forEach(slider => {
            let gainTimeout = null;
            
            slider.addEventListener('input', (e) => {
                const val = parseFloat(e.target.value).toFixed(1);
                const deviceId = e.target.dataset.deviceId;
                const deviceUuid = e.target.dataset.deviceUuid;
                const card = document.querySelector(`[data-device-id="${deviceId}"]`);
                
                // Update display
                card.querySelector('.gain-value-display').innerText = val + 'x';
                
                clearTimeout(gainTimeout);
                
                // 1. Instant MQTT Command
                const controlTopic = `audio/${deviceId}/control`;
                const payload = JSON.stringify({ action: 'set_gain', value: parseFloat(val) });
                client.publish(controlTopic, payload);
                
                // 2. Debounced API call to persist
                gainTimeout = setTimeout(async () => {
                    try {
                        await fetch(`/devices/${deviceUuid}/gain`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ gain: val })
                        });
                    } catch (err) {
                        console.error('Failed to update gain:', err);
                    }
                }, 1000);
            });
        });

        // --- Threshold Slider Logic ---
        document.querySelectorAll('.threshold-slider').forEach(slider => {
            let thresholdTimeout = null;
            
            slider.addEventListener('input', (e) => {
                const val = parseFloat(e.target.value).toFixed(1);
                const deviceId = e.target.dataset.deviceId;
                const deviceUuid = e.target.dataset.deviceUuid;
                const card = document.querySelector(`[data-device-id="${deviceId}"]`);
                
                // Update display
                card.querySelector('.threshold-value-display').innerText = val + ' dB';
                
                // Sync with Map & Stats
                @if($devicesWithLocation->count() > 0)
                if (markers[deviceId]) {
                    const mkData = markers[deviceId].data;
                    mkData.threshold = parseFloat(val);
                    // Re-evaluate threshold status
                    if (mkData.latest_db_spl != null) {
                        mkData.exceeding_threshold = mkData.latest_db_spl >= mkData.threshold;
                        // Update marker appearance immediately
                        updateMarker(deviceId, {}); // Pass empty data to trigger re-render with existing data
                    }
                }
                @endif
                
                // Update stats
                updateStatistics();
                
                clearTimeout(thresholdTimeout);
                
                // Debounced API call to persist
                thresholdTimeout = setTimeout(async () => {
                    try {
                        await fetch(`/devices/${deviceUuid}/threshold`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ threshold: val })
                        });
                    } catch (err) {
                        console.error('Failed to update threshold:', err);
                    }
                }, 1000);
            });
        });

        // --- Recording Controls Logic (Dashboard) ---
        function updateRecordingState(deviceId, isRecording, isOnline) {
            const card = document.querySelector(`[data-device-id="${deviceId}"]`);
            if (!card) return;

            const startBtn = card.querySelector('.btn-start-rec');
            const stopBtn = card.querySelector('.btn-stop-rec');
            const indicator = card.querySelector('.recording-indicator');

            if (isRecording) {
                startBtn.disabled = true;
                stopBtn.disabled = false;
                indicator.classList.remove('hidden');
            } else {
                startBtn.disabled = !isOnline; // Only enable if device is online
                stopBtn.disabled = true;
                indicator.classList.add('hidden');
            }
        }

        // Handle clicks
        document.querySelectorAll('.btn-start-rec').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                const deviceId = btn.dataset.deviceUuid; // Ensure this is UUID for route
                const mqttId = btn.dataset.deviceId;
                
                // Optimistic UI update
                updateRecordingState(mqttId, true, true);

                try {
                    await fetch(`/devices/${deviceId}/record/start`, { // Assuming route exists
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                } catch (err) {
                    console.error('Failed to start recording', err);
                    updateRecordingState(mqttId, false, true); // Revert on fail
                }
            });
        });

        document.querySelectorAll('.btn-stop-rec').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                const deviceId = btn.dataset.deviceUuid;
                const mqttId = btn.dataset.deviceId;
                
                updateRecordingState(mqttId, false, true);

                try {
                    await fetch(`/devices/${deviceId}/record/stop`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                } catch (err) {
                    console.error('Failed to stop recording', err);
                }
            });
        });
    </script>
    @endpush
</x-app-layout>
