<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SessionSecurity
{
    public function invalidateOtherSessions(User $user, ?string $currentSessionId): void
    {
        if (config('session.driver') !== 'database' || ! Schema::hasTable(config('session.table', 'sessions'))) {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->when($currentSessionId, fn ($query) => $query->where('id', '!=', $currentSessionId))
            ->delete();
    }

    public function invalidateAllSessions(User $user): void
    {
        $this->invalidateOtherSessions($user, null);
    }
}
