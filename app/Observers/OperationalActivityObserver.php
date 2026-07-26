<?php

namespace App\Observers;

use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OperationalActivityObserver
{
    public function created(Model $model): void
    {
        $this->write($model, 'created', 'dibuat');
    }

    public function updated(Model $model): void
    {
        $this->write($model, 'updated', 'diperbarui');
    }

    public function deleted(Model $model): void
    {
        $this->write($model, 'deleted', 'diarsipkan');
    }

    private function write(Model $model, string $event, string $description): void
    {
        $actor = auth()->user();

        if (! $actor) {
            return;
        }

        $subject = Str::snake(class_basename($model));
        $changedFields = array_values(array_diff(
            array_keys($model->getChanges()),
            ['updated_at', 'password', 'remember_token'],
        ));

        app(ActivityLogger::class)->log(
            "{$subject}.{$event}",
            Str::headline($subject)." {$description}.",
            $actor,
            $model,
            ['changed_fields' => $changedFields],
        );
    }
}
