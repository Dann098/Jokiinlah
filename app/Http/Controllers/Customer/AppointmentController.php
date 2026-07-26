<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $appointments = Appointment::query()
            ->where('customer_id', $user->id)
            ->whereHas('project', fn ($project) => $project->where('customer_id', $user->id))
            ->with('project:id,project_code,title')
            ->orderByDesc('appointment_date')
            ->paginate(12);

        return view('customer.appointments.index', compact('appointments'));
    }
}
