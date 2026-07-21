<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    public function log(string $action, string $description, ?User $actor = null, ?Model $subject = null, array $metadata = []): ActivityLog
    {
        return ActivityLog::query()->forceCreate([
            'user_id' => $actor?->id,
            'action' => $action,
            'description' => $description,
            'model_type' => $subject?->getMorphClass(),
            'model_id' => $subject?->getKey(),
            'metadata' => $metadata,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
