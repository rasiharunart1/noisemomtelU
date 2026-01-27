<x-app-layout>
    <x-slot name="header">
        Settings
    </x-slot>

    <div class="max-w-3xl mx-auto">
        <div class="glass-card p-8 rounded-2xl" x-data="{
            testing: false,
            testResult: null,
            async testConnection() {
                this.testing = true;
                this.testResult = null;
                try {
                    const res = await fetch('{{ route('settings.check_connection') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            mqtt_host: document.getElementById('mqtt_host').value,
                            mqtt_port: document.getElementById('mqtt_port').value,
                            mqtt_username: document.getElementById('mqtt_username').value,
                            mqtt_password: document.getElementById('mqtt_password').value
                        })
                    });
                    const data = await res.json();
                    this.testResult = { success: data.success, message: data.message };
                } catch (e) {
                    this.testResult = { success: false, message: 'Connection Request Failed' };
                } finally {
                    this.testing = false;
                }
            }
        }">
            <form action="{{ route('settings.update') }}" method="POST">
                @csrf

                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-6">MQTT Configuration</h3>

                <!-- MQTT Host -->
                <div class="mb-6">
                    <label for="mqtt_host" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        MQTT Broker Host <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="mqtt_host" id="mqtt_host" value="{{ old('mqtt_host', $settings['mqtt_host']) }}" required
                           class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg focus:ring-2 focus:ring-red-500 text-gray-900 dark:text-white font-mono text-sm"
                           placeholder="broker.hivemq.com">
                    @error('mqtt_host')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- MQTT Port -->
                <div class="mb-6">
                    <label for="mqtt_port" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        MQTT Port <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="mqtt_port" id="mqtt_port" value="{{ old('mqtt_port', $settings['mqtt_port']) }}" required
                           class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg focus:ring-2 focus:ring-red-500 text-gray-900 dark:text-white"
                           placeholder="8883">
                    @error('mqtt_port')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">8883 for SSL, 1883 for non-SSL</p>
                </div>

                <!-- MQTT Username -->
                <div class="mb-6">
                    <label for="mqtt_username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        MQTT Username
                    </label>
                    <input type="text" name="mqtt_username" id="mqtt_username" value="{{ old('mqtt_username', $settings['mqtt_username']) }}"
                           class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg focus:ring-2 focus:ring-red-500 text-gray-900 dark:text-white">
                    @error('mqtt_username')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- MQTT Password -->
                <div class="mb-6">
                    <label for="mqtt_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        MQTT Password
                    </label>
                    <input type="password" name="mqtt_password" id="mqtt_password" 
                           class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg focus:ring-2 focus:ring-red-500 text-gray-900 dark:text-white"
                           placeholder="Leave empty to keep current password">
                    @error('mqtt_password')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Test Connection Button -->
                <div class="mb-6 flex items-center justify-between bg-gray-50 dark:bg-white/5 p-4 rounded-lg border border-gray-200 dark:border-white/10">
                    <div class="flex-1 mr-4">
                        <p class="text-sm font-medium text-gray-900 dark:text-white" x-text="testing ? 'Testing connection...' : (testResult ? testResult.message : 'Test MQTT Connection')"></p>
                        <p class="text-xs mt-1" :class="testResult && testResult.success ? 'text-emerald-500' : (testResult && !testResult.success ? 'text-red-500' : 'text-gray-500')">
                            <span x-show="!testResult && !testing">Click to verify connectivity with current settings</span>
                            <span x-show="testing">Please wait...</span>
                        </p>
                    </div>
                    <button type="button" @click="testConnection()" :disabled="testing"
                            class="px-4 py-2 bg-gray-200 dark:bg-white/10 hover:bg-gray-300 dark:hover:bg-white/20 text-gray-700 dark:text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-50">
                        Test Connection
                    </button>
                </div>

                <!-- MQTT Topic Pattern -->
                <div class="mb-6">
                    <label for="mqtt_topic_pattern" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        MQTT Topic Pattern <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="mqtt_topic_pattern" id="mqtt_topic_pattern" value="{{ old('mqtt_topic_pattern', $settings['mqtt_topic_pattern']) }}" required
                           class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg focus:ring-2 focus:ring-red-500 text-gray-900 dark:text-white font-mono text-sm"
                           placeholder="audio/+/data">
                    @error('mqtt_topic_pattern')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Use + for single-level wildcard, # for multi-level</p>
                </div>

                <hr class="my-8 border-white/10">

                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-6">Logging Configuration</h3>

                <!-- FFT Logging Interval -->
                <div class="mb-6">
                    <label for="fft_logging_interval" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        FFT Logging Interval (seconds) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="fft_logging_interval" id="fft_logging_interval" value="{{ old('fft_logging_interval', $settings['fft_logging_interval']) }}" required min="1" max="300"
                           class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg focus:ring-2 focus:ring-red-500 text-gray-900 dark:text-white">
                    @error('fft_logging_interval')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">How often to save FFT data to database (1-300 seconds)</p>
                </div>

                <!-- Info Box -->
                <div class="bg-blue-500/10 border border-blue-500/30 rounded-lg p-4 mb-6">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-blue-500 mt-0.5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-blue-800 dark:text-blue-300">Important Notice</p>
                            <p class="text-xs text-blue-700 dark:text-blue-400 mt-1">
                                After changing MQTT settings, you must restart the MQTT listener service for changes to take effect:
                                <code class="bg-blue-900/30 px-2 py-1 rounded text-blue-300">php artisan mqtt:listen</code>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end">
                    <button type="submit" class="btn-primary">
                        Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
