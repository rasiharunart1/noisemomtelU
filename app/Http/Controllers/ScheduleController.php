<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\RecordingSchedule;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Device $device)
    {
        $validated = $request->validate([
            'start_time' => 'required|date|after:now',
            'end_time' => 'required|date|after:start_time',
            'interval_minutes' => 'required|integer|min:1',
            'duration_seconds' => 'required|integer|min:5|max:300',
        ]);

        $device->schedules()->create([
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
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
