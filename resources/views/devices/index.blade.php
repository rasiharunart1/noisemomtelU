<x-app-layout>
    <x-slot name="header">
        Devices Management
    </x-slot>

    <!-- Devices Map Widget -->
    <!-- Devices Map Widget -->
    <div class="mb-8 rounded-2xl glass-card overflow-hidden relative">
        <div id="devices-map" class="w-full h-96 z-0"></div>
        
        <!-- Map Legend -->
        <div class="absolute bottom-4 left-4 z-[400] flex space-x-2 pointer-events-none">
            <div class="bg-white/90 dark:bg-gray-900/90 backdrop-blur px-3 py-1.5 rounded-lg text-xs font-medium text-gray-600 dark:text-gray-300 shadow-lg flex items-center border border-gray-200 dark:border-gray-700">
                <span class="w-3 h-3 rounded-full bg-yellow-400 mr-2 border border-yellow-600"></span> Online
            </div>
            <div class="bg-white/90 dark:bg-gray-900/90 backdrop-blur px-3 py-1.5 rounded-lg text-xs font-medium text-gray-600 dark:text-gray-300 shadow-lg flex items-center border border-gray-200 dark:border-gray-700">
                <span class="w-3 h-3 rounded-full bg-red-600 mr-2 border border-red-800"></span> Offline
            </div>
        </div>
    </div>

    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <style>
            #devices-map { z-index: 0; background: transparent; }
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
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            function controlRecording(deviceIdStr, dbId, action) {
                if(!confirm(`Are you sure you want to ${action} recording for ${deviceIdStr}?`)) return;

                const url = `/devices/${dbId}/record/${action}`;
                const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if(data.success) {
                        alert(`Success: ${data.message}`);
                    } else {
                        alert(`Error: ${data.message || 'Unknown error'}`);
                    }
                })
                .catch(err => {
                    alert('Failed to send command');
                    console.error(err);
                });
            }

            document.addEventListener('DOMContentLoaded', function() {
                // Initialize Map
                // Default Center: Telkom University or calculate bounds later
                var map = L.map('devices-map').setView([-6.9744, 107.6303], 13);
                map.zoomControl.setPosition('bottomright');
                
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

                // Custom Icons Function
                function createCustomIcon(status) {
                    const color = status === 'online' ? '#fbbf24' : '#ef4444'; // Amber-400 : Red-500
                    const html = `
                        <div class="custom-marker">
                            <div class="marker-pulse" style="background-color: ${color}"></div>
                            <div class="marker-pin" style="background-color: ${color}"></div>
                        </div>
                    `;
                    return L.divIcon({
                        html: html,
                        className: '',
                        iconSize: [32, 32],
                        iconAnchor: [16, 16]
                    });
                }

                var markers = [];
                var bounds = L.latLngBounds();

                // Devices Data
                var devices = @json($devices);

                devices.forEach(function(device) {
                    if (device.latitude && device.longitude) {
                        var marker = L.marker([device.latitude, device.longitude], {
                            icon: createCustomIcon(device.status)
                        }).bindPopup(`
                            <div class="p-1 min-w-[150px]">
                                <div class="flex items-center space-x-2 mb-2">
                                    <div class="w-2 h-2 rounded-full ${device.status === 'online' ? 'bg-yellow-400' : 'bg-red-500'}"></div>
                                    <h3 class="font-bold text-gray-900 dark:text-white text-sm">${device.name ?? 'Device'}</h3>
                                </div>
                                <p class="text-[10px] text-gray-500 font-mono mb-3">${device.device_id}</p>
                                <a href="/devices/${device.id}" class="block w-full text-center px-4 py-2 bg-red-600 text-white text-xs font-bold rounded-lg hover:bg-red-700 transition shadow-lg shadow-red-500/20">
                                    Lihat Detail
                                </a>
                            </div>
                        `, {
                            maxWidth: 250,
                            closeButton: false
                        });
                        
                        marker.addTo(map);
                        markers.push(marker);
                        bounds.extend([device.latitude, device.longitude]);
                    }
                });

                // Fit bounds if markers exist
                if (markers.length > 0) {
                    map.fitBounds(bounds, { padding: [50, 50] });
                }
            });
        </script>
    @endpush

    <div class="glass-card p-6 rounded-2xl">
        <!-- Header with Add Button -->
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-gray-900 dark:text-white">All Devices</h3>
            <a href="{{ route('devices.create') }}" class="btn-primary">
                <svg class="w-5 h-5 inline mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"></path>
                </svg>
                Add Device
            </a>
        </div>



        <!-- Devices Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs uppercase bg-white/5">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-gray-700 dark:text-gray-300">Device ID</th>
                        <th scope="col" class="px-6 py-3 text-gray-700 dark:text-gray-300">Name</th>
                        <th scope="col" class="px-6 py-3 text-gray-700 dark:text-gray-300">MQTT Topic</th>
                        <th scope="col" class="px-6 py-3 text-gray-700 dark:text-gray-300">Status</th>
                        <th scope="col" class="px-6 py-3 text-gray-700 dark:text-gray-300">Last Seen</th>
                        <th scope="col" class="px-6 py-3 text-gray-700 dark:text-gray-300">Logs</th>
                        <th scope="col" class="px-6 py-3 text-gray-700 dark:text-gray-300">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($devices as $device)
                        <tr class="border-b border-white/10 hover:bg-white/5">
                            <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                {{ $device->device_id }}
                            </td>
                            <td class="px-6 py-4 text-gray-900 dark:text-white">
                                {{ $device->name ?? '-' }}
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-gray-600 dark:text-gray-400">
                                {{ $device->mqtt_topic }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $device->status === 'online' ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400' : 'bg-gray-500/20 text-gray-600 dark:text-gray-400' }}">
                                    {{ ucfirst($device->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                                {{ $device->last_seen ? $device->last_seen->diffForHumans() : 'Never' }}
                            </td>
                            <td class="px-6 py-4 text-gray-900 dark:text-white">
                                {{ $device->fft_logs_count }}
                            </td>
                            <td class="px-6 py-4">
                                    <!-- Recording Controls -->
                                    <button onclick="controlRecording('{{ $device->device_id }}', '{{ $device->id }}', 'start')" class="text-red-500 hover:text-red-700 dark:text-red-400 bg-red-100 dark:bg-red-900/30 p-1.5 rounded-lg transition-colors" title="Start Recording">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM10 9a1 1 0 011 1v4a1 1 0 11-2 0v-4a1 1 0 011-1z" clip-rule="evenodd" style="display:none"></path>
                                            <circle cx="10" cy="10" r="4"></circle>
                                        </svg>
                                    </button>
                                    <button onclick="controlRecording('{{ $device->device_id }}', '{{ $device->id }}', 'stop')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 p-1.5 rounded-lg transition-colors" title="Stop Recording">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <rect x="6" y="6" width="8" height="8" rx="1"></rect>
                                        </svg>
                                    </button>
                                    <div class="h-4 w-px bg-gray-300 dark:bg-gray-700 mx-1"></div>

                                    <a href="{{ route('devices.show', $device) }}" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400" title="View">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"></path>
                                            <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"></path>
                                        </svg>
                                    </a>
                                    <a href="{{ route('devices.edit', $device) }}" class="text-yellow-600 hover:text-yellow-900 dark:text-yellow-400" title="Edit">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path>
                                        </svg>
                                    </a>
                                    <form action="{{ route('devices.destroy', $device) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this device?');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900 dark:text-red-400" title="Delete">
                                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <p class="text-gray-500 dark:text-gray-400">No devices found. Add your first device to get started.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
