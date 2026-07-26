<?php

namespace App\Services;

use App\Models\Project;
use App\Models\SiteSetting;

class WhatsAppUrlBuilder
{
    public function number(): ?string
    {
        $number = SiteSetting::query()->where('key', 'whatsapp_number')->value('value')
            ?: config('jokiinlah.whatsapp_number');
        $digits = preg_replace('/\D+/', '', (string) $number) ?? '';

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        return preg_match('/^62[0-9]{8,13}$/', $digits) ? $digits : null;
    }

    public function build(string $message): ?string
    {
        $number = $this->number();

        return $number ? 'https://wa.me/'.$number.'?text='.rawurlencode(trim($message)) : null;
    }

    public function forProject(Project $project): ?string
    {
        return $this->build(sprintf(
            'Halo, saya ingin menanyakan perkembangan proyek %s — %s.',
            $project->project_code,
            $project->title,
        ));
    }
}
