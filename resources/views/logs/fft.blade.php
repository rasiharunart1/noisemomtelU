<x-app-layout>
    <x-slot name="header">
        FFT Logs
    </x-slot>

    <div class="glass-card p-6 rounded-2xl mb-6">
        <!-- Filter Form -->
        <form method="GET" action="{{ route('logs.fft') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <!-- Device Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Device</label>
                <select name="device_id" class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg focus:ring-2 focus:ring-purple-500 text-gray-900 dark:text-white">
                    <option value="">All Devices</option>
                    @foreach($devices as $device)
                        <option value="{{ $device->id }}" {{ request('device_id') == $device->id ? 'selected' : '' }}>
                            {{ $device->device_id }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Start Date -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Start Date</label>
                <input type="date" name="start_date" value="{{ request('start_date') }}"
                       class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg focus:ring-2 focus:ring-purple-500 text-gray-900 dark:text-white">
            </div>

            <!-- End Date -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">End Date</label>
                <input type="date" name="end_date" value="{{ request('end_date') }}"
                       class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg focus:ring-2 focus:ring-purple-500 text-gray-900 dark:text-white">
            </div>

            <!-- Min Frequency -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Min Freq (Hz)</label>
                <input type="number" name="min_frequency" value="{{ request('min_frequency') }}" step="0.1"
                       class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg focus:ring-2 focus:ring-purple-500 text-gray-900 dark:text-white">
            </div>

            <!-- Filter Buttons -->
            <div class="flex items-end space-x-2">
                <button type="submit" class="flex-1 btn-primary">
                    Filter
                </button>
                <a href="{{ route('logs.fft') }}" class="flex-1 btn-secondary text-center">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Export Button -->
    <div class="mb-4">
        <a href="{{ route('logs.fft.export', request()->all()) }}" class="btn-primary inline-block">
            <svg class="w-5 h-5 inline mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"></path>
            </svg>
            Export CSV
        </a>
    </div>

    <!-- Logs Table -->
    <div class="glass-card p-6 rounded-2xl">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-xs uppercase bg-white/5">
                    <tr>
                        <th class="px-4 py-3 text-left text-gray-700 dark:text-gray-300">Timestamp</th>
                        <th class="px-4 py-3 text-left text-gray-700 dark:text-gray-300">Device</th>
                        <th class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">RMS</th>
                        <th class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">Peak Amp</th>
                        <th class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">Peak Freq</th>
                        <th class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">Total Energy</th>
                        <th class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">Centroid</th>
                        <th class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">ZCR</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr class="border-b border-white/10 hover:bg-white/5">
                            <td class="px-4 py-3 text-gray-900 dark:text-white">
                                {{ $log->created_at->format('Y-m-d H:i:s') }}
                            </td>
                            <td class="px-4 py-3 text-gray-900 dark:text-white">
                                {{ $log->device->device_id ?? 'Unknown' }}
                            </td>
                            <td class="px-4 py-3 text-right text-gray-900 dark:text-white">
                                {{ number_format($log->rms, 3) }}
                            </td>
                            <td class="px-4 py-3 text-right text-gray-900 dark:text-white">
                                {{ number_format($log->peak_amplitude, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-purple-600 dark:text-purple-400">
                                {{ number_format($log->peak_frequency, 1) }} Hz
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-indigo-600 dark:text-indigo-400">
                                {{ number_format($log->total_energy, 0) }}
                            </td>
                            <td class="px-4 py-3 text-right text-gray-900 dark:text-white">
                                {{ number_format($log->spectral_centroid, 1) }}
                            </td>
                            <td class="px-4 py-3 text-right text-gray-900 dark:text-white">
                                {{ number_format($log->zcr, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                No logs found. Try adjusting your filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-6">
            {{ $logs->links() }}
        </div>
    </div>
</x-app-layout>
