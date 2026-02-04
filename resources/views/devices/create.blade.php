<x-app-layout>
    <x-slot name="header">
        Add New Device
    </x-slot>

    <div class="max-w-2xl mx-auto">
        <div class="glass-card p-8 rounded-2xl">
            <form action="{{ route('devices.store') }}" method="POST">
                @csrf

                <!-- Name -->
                <div class="mb-6">
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Device Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required
                           class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 text-gray-900 dark:text-white placeholder-gray-500"
                           placeholder="Telkom Hall Sensor">
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Device ID & MQTT Topic will be automatically generated.
                    </p>
                    @error('name')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Description -->
                <div class="mb-6">
                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Keterangan
                    </label>
                    <textarea name="description" id="description" rows="3"
                              class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 text-gray-900 dark:text-white placeholder-gray-500">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Location -->
                <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="latitude" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Latitude
                        </label>
                        <input type="text" name="latitude" id="latitude" value="{{ old('latitude') }}"
                               class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 text-gray-900 dark:text-white placeholder-gray-500"
                               placeholder="-6.2088">
                        @error('latitude')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="longitude" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Longitude
                        </label>
                        <input type="text" name="longitude" id="longitude" value="{{ old('longitude') }}"
                               class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 text-gray-900 dark:text-white placeholder-gray-500"
                               placeholder="106.8456">
                        @error('longitude')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Pin Location on Map
                    </label>
                    <div class="relative w-full h-80 rounded-xl overflow-hidden glass-card p-1 group">
                         <!-- Search & GPS Controls Overlay -->
                         <div class="absolute top-4 left-4 right-4 z-[400] flex gap-2">
                            <div class="relative flex-1">
                                <input type="text" id="map-search" 
                                       class="w-full pl-10 pr-4 py-2 bg-white/90 dark:bg-gray-900/90 backdrop-blur border border-gray-200 dark:border-gray-700 rounded-lg text-sm text-gray-700 dark:text-gray-200 placeholder-gray-500 focus:ring-2 focus:ring-red-500 shadow-lg"
                                       placeholder="Search location (e.g. Telkom University)..."
                                       autocomplete="off">
                                <svg class="w-4 h-4 text-gray-500 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                <!-- Search Results Dropdown -->
                                <div id="search-results" class="hidden absolute top-full left-0 right-0 mt-2 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 max-h-60 overflow-y-auto z-[500]"></div>
                            </div>
                            
                            <button type="button" id="btn-gps" class="bg-white/90 dark:bg-gray-900/90 backdrop-blur p-2 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 hover:bg-red-50 dark:hover:bg-red-900/20 text-gray-600 dark:text-gray-300 hover:text-red-600 transition-colors" title="Use My Location">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </button>
                         </div>

                         <div id="map" class="w-full h-full rounded-lg z-0 relative"></div>
                         
                         <!-- Map Controls Hint -->
                         <div class="absolute bottom-4 left-4 z-[400] bg-white/90 dark:bg-gray-900/90 backdrop-blur px-3 py-1.5 rounded-lg text-xs font-medium text-gray-600 dark:text-gray-300 shadow-lg border border-gray-200 dark:border-gray-700 pointer-events-none opacity-70 group-hover:opacity-100 transition-opacity">
                             Click map to pin
                         </div>
                    </div>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Use search or GPS button to find places quickly.
                    </p>
                </div>

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
                    </style>
                @endpush

                @push('scripts')
                    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            // Default: Jakarta (-6.2088, 106.8456)
                            // Telkom University Landmark (-6.9744, 107.6303)
                            
                            var defaultCenter = [-6.9744, 107.6303]; // Telkom Bandung
                            var map = L.map('map').setView(defaultCenter, 13);
                            map.zoomControl.setPosition('bottomright');

                            // Tiles: Use CartoDB Positron for a cleaner look
                            const isDark = document.documentElement.classList.contains('dark');
                            const tileUrl = isDark 
                                ? 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png'
                                : 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png';

                            L.tileLayer(tileUrl, {
                                maxZoom: 19,
                                attribution: '&copy; CartoDB'
                            }).addTo(map);

                            var marker;
                            
                            // Custom Icon for Telkom Theme
                            function createCustomIcon() {
                                return L.divIcon({
                                    html: `
                                        <div class="custom-marker">
                                            <div class="marker-pulse" style="background-color: #ef4444"></div>
                                            <div class="marker-pin" style="background-color: #ef4444"></div>
                                        </div>
                                    `,
                                    className: '',
                                    iconSize: [32, 32],
                                    iconAnchor: [16, 16]
                                });
                            }

                            function updateMarker(lat, lng) {
                                if (marker) {
                                    marker.setLatLng([lat, lng]);
                                } else {
                                    marker = L.marker([lat, lng], {icon: createCustomIcon()}).addTo(map);
                                }
                                document.getElementById('latitude').value = lat.toFixed(6);
                                document.getElementById('longitude').value = lng.toFixed(6);
                            }

                            map.on('click', function(e) {
                                updateMarker(e.latlng.lat, e.latlng.lng);
                            });

                            // Initialize if old values exist
                            var initialLat = document.getElementById('latitude').value;
                            var initialLng = document.getElementById('longitude').value;
                            if (initialLat && initialLng) {
                                var lat = parseFloat(initialLat);
                                var lng = parseFloat(initialLng);
                                updateMarker(lat, lng);
                                map.setView([lat, lng], 15);
                            }

                            // Sync manual inputs
                             document.getElementById('latitude').addEventListener('change', function() {
                                var lat = parseFloat(this.value);
                                var lng = parseFloat(document.getElementById('longitude').value);
                                if (!isNaN(lat) && !isNaN(lng)) {
                                    updateMarker(lat, lng);
                                    map.panTo([lat, lng]);
                                }
                            });
                             document.getElementById('longitude').addEventListener('change', function() {
                                var lng = parseFloat(this.value);
                                var lat = parseFloat(document.getElementById('latitude').value);
                                if (!isNaN(lat) && !isNaN(lng)) {
                                    updateMarker(lat, lng);
                                    map.panTo([lat, lng]);
                                }
                            });

                            // --- Search Functionality ---
                            const searchInput = document.getElementById('map-search');
                            const searchResults = document.getElementById('search-results');
                            let searchTimeout;

                            searchInput.addEventListener('input', function() {
                                clearTimeout(searchTimeout);
                                const query = this.value;
                                
                                if (query.length < 3) {
                                    searchResults.classList.add('hidden');
                                    return;
                                }

                                searchTimeout = setTimeout(() => {
                                    fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`)
                                        .then(response => response.json())
                                        .then(data => {
                                            searchResults.innerHTML = '';
                                            if (data.length > 0) {
                                                searchResults.classList.remove('hidden');
                                                data.slice(0, 5).forEach(result => {
                                                    const div = document.createElement('div');
                                                    div.className = 'px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer text-sm text-gray-700 dark:text-gray-200 border-b border-gray-100 dark:border-gray-700 last:border-0';
                                                    div.textContent = result.display_name;
                                                    div.addEventListener('click', () => {
                                                        const lat = parseFloat(result.lat);
                                                        const lon = parseFloat(result.lon);
                                                        updateMarker(lat, lon);
                                                        map.setView([lat, lon], 16);
                                                        searchResults.classList.add('hidden');
                                                        searchInput.value = result.display_name;
                                                    });
                                                    searchResults.appendChild(div);
                                                });
                                            } else {
                                                searchResults.classList.add('hidden');
                                            }
                                        });
                                }, 500);
                            });

                            // Hide results on click outside
                            document.addEventListener('click', function(e) {
                                if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                                    searchResults.classList.add('hidden');
                                }
                            });

                            // --- GPS Functionality ---
                            document.getElementById('btn-gps').addEventListener('click', function() {
                                if ("geolocation" in navigator) {
                                    const btn = this;
                                    btn.classList.add('animate-pulse', 'text-red-500');
                                    
                                    navigator.geolocation.getCurrentPosition(function(position) {
                                        const lat = position.coords.latitude;
                                        const lng = position.coords.longitude;
                                        updateMarker(lat, lng);
                                        map.setView([lat, lng], 16);
                                        btn.classList.remove('animate-pulse', 'text-red-500');
                                    }, function(error) {
                                        alert("Error getting location: " + error.message);
                                        btn.classList.remove('animate-pulse', 'text-red-500');
                                    });
                                } else {
                                    alert("Geolocation is not available in your browser.");
                                }
                            });
                        });
                    </script>
                @endpush

                <!-- Buttons -->
                <div class="flex items-center justify-end space-x-4">
                    <a href="{{ route('devices.index') }}" class="btn-secondary">
                        Cancel
                    </a>
                    <button type="submit" class="btn-primary">
                        Add Device
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
