<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\RecordingSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ScheduleController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Device $device)
    {
        $validated = $request->validate([
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'interval_minutes' => 'required|integer|min:1',
            'duration_seconds' => 'required|integer|min:5|max:14400',
        ]);

        // The form's datetime-local values are in the user's local time
        // (WIB); the app stores and compares timestamps in UTC.
        $tz = config('app.display_timezone');
        $start = Carbon::parse($validated['start_time'], $tz)->utc();
        $end = Carbon::parse($validated['end_time'], $tz)->utc();

        if ($start->lessThan(now()->subMinute())) {
            return back()->withErrors(['start_time' => 'Start time must be in the future.'])->withInput();
        }

        // The device ignores start_recording while it is still recording
        if ($validated['interval_minutes'] * 60 < $validated['duration_seconds']) {
            return back()->withErrors([
                'interval_minutes' => 'Interval must be at least as long as the recording duration.',
            ])->withInput();
        }

        $device->schedules()->create([
            'start_time' => $start,
            'end_time' => $end,
            'interval_minutes' => $validated['interval_minutes'],
            'duration_seconds' => $validated['duration_seconds'],
            'status' => 'pending',
        ]);

        return redirect()->back()->with('success', 'Recording schedule created successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RecordingSchedule $schedule)
    {
        $schedule->delete();

        return redirect()->back()->with('success', 'Schedule cancelled successfully.');
    }
}
