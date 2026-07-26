<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Reminder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $reminders = Reminder::query()
            ->where('user_id', $user->id)
            ->where(function ($query) use ($user): void {
                $query->whereNull('project_id')
                    ->orWhereHas('project', fn ($project) => $project->where('customer_id', $user->id));
            })
            ->with('project:id,project_code,title')
            ->orderBy('is_completed')
            ->orderBy('reminder_date')
            ->paginate(12);

        return view('customer.reminders.index', compact('reminders'));
    }
}
