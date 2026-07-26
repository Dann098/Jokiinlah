<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    public function log(string $action, string $description, ?User $actor = null, ?Model $subject = null, array $metadata = []): ActivityLog
    {
        $ipAddress = request()?->ip();

        return ActivityLog::query()->forceCreate([
            'user_id' => $actor?->id,
            'action' => $action,
            'description' => $description,
            'model_type' => $subject?->getMorphClass(),
            'model_id' => $subject?->getKey(),
            'metadata' => $metadata,
            'ip_address' => $ipAddress ? 'sha256:'.substr(hash('sha256', $ipAddress), 0, 32) : null,
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
