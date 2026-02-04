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

    <!-- Archived Logs Section -->
    <div class="glass-card p-6 rounded-2xl mb-6 bg-blue-500/5 border border-blue-500/10" x-data="{ selected: [], selectAll: false }">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center">
                <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                </svg>
                Archived Daily Logs
                <span class="ml-2 text-sm font-normal text-gray-500">({{ count($archives) }} files)</span>
            </h3>
            
            @if(count($archives) > 0)
            <form action="{{ route('logs.fft.bulk_download') }}" method="POST" x-ref="bulkForm">
                @csrf
                <template x-for="filename in selected">
                    <input type="hidden" name="files[]" :value="filename">
                </template>
                <button type="submit" 
                        x-show="selected.length > 0"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition-colors flex items-center"
                        style="display: none;">
                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Download <span x-text="selected.length"></span> File(s)
                </button>
            </form>
            @endif
        </div>

        @if(count($archives) > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs uppercase text-gray-500 border-b border-gray-200 dark:border-white/10">
                        <th class="px-4 py-2 text-left w-10">
                            <input type="checkbox" 
                                   x-model="selectAll"
                                   @change="selectAll ? selected = {{ json_encode(array_column($archives, 'filename')) }} : selected = []"
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        </th>
                        <th class="px-4 py-2 text-left">Filename</th>
                        <th class="px-4 py-2 text-left">Date Archived</th>
                        <th class="px-4 py-2 text-right">Size</th>
                        <th class="px-4 py-2 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach($archives as $archive)
                    <tr class="hover:bg-white/5 transition-colors">
                        <td class="px-4 py-3">
                            <input type="checkbox" 
                                   value="{{ $archive['filename'] }}"
                                   x-model="selected"
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        </td>
                        <td class="px-4 py-3 font-mono text-gray-700 dark:text-gray-300 text-xs">{{ $archive['filename'] }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ date('M d, Y H:i', $archive['last_modified']) }}</td>
                        <td class="px-4 py-3 text-right text-gray-500">{{ number_format($archive['size'] / 1024, 2) }} KB</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('logs.fft.download_archive', $archive['filename']) }}" 
                               class="text-blue-600 hover:text-blue-500 font-medium text-xs">
                                Download
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center py-12">
            <svg class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
            </svg>
            <p class="text-gray-500 dark:text-gray-400 font-medium">No archived logs yet</p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Enable auto-archive in Settings to start archiving logs automatically</p>
        </div>
        @endif
    </div>

    <div class="mb-4 flex justify-between items-center">
        <a href="{{ route('logs.fft.export', request()->all()) }}" class="btn-primary inline-block">
            <svg class="w-5 h-5 inline mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"></path>
            </svg>
            Export CSV
        </a>

        <form action="{{ route('logs.fft.destroy_all') }}" method="POST" onsubmit="return confirm('WARNING: This will permanently delete ALL FFT logs from the database. This action cannot be undone. Are you sure you want to proceed?');" class="inline">
            @csrf
            @method('DELETE')
            <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium transition-colors flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                Reset All Logs
            </button>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="glass-card p-6 rounded-2xl">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-xs uppercase bg-white/5">
                    <tr>
                        <th class="px-4 py-3 text-left text-gray-700 dark:text-gray-300">Timestamp</th>
                        <th class="px-4 py-3 text-left text-gray-700 dark:text-gray-300">Device</th>
                        <th class="px-4 py-3 text-left text-gray-700 dark:text-gray-300">Keterangan</th>
                        <th class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">RMS</th>
                        <th class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">dB SPL</th>
                        <th class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">Peak Freq</th>
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
                            <td class="px-4 py-3 text-gray-900 dark:text-white">
                                {{ $log->device->description ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-right text-gray-900 dark:text-white">
                                {{ number_format($log->rms, 3) }}
                            </td>
                            <td class="px-4 py-3 text-right text-gray-900 dark:text-white">
                                {{ number_format($log->db_spl, 1) }} dB
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-purple-600 dark:text-purple-400">
                                {{ number_format($log->peak_frequency, 1) }} Hz
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
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
