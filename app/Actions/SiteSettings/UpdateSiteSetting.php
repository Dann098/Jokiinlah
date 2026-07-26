<?php

namespace App\Actions\SiteSettings;

use App\Models\SiteSetting;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateSiteSetting
{
    public function __construct(private ActivityLogger $logger) {}

    public function execute(SiteSetting $setting, mixed $value, User $actor): SiteSetting
    {
        if (! $actor->isAdmin()) {
            throw new AuthorizationException('Hanya admin dapat memperbarui pengaturan situs.');
        }

        $allowed = config('jokiinlah.editable_site_settings', []);
        if (! array_key_exists($setting->key, $allowed) || preg_match('/password|secret|token|credential/i', $setting->key)) {
            throw ValidationException::withMessages(['value' => 'Pengaturan ini tidak boleh diubah dari panel.']);
        }

        $rules = match ($setting->key) {
            'whatsapp_number' => ['required', 'regex:/^\+?[0-9]{9,15}$/'],
            'contact_email' => ['required', 'email:rfc', 'max:255'],
            'brand_name' => ['required', 'string', 'max:100'],
            'brand_tagline' => ['required', 'string', 'max:255'],
            'academic_integrity_notice' => ['required', 'string', 'max:5000'],
            default => ['prohibited'],
        };

        $validated = Validator::make(['value' => $value], ['value' => $rules], [
            'value.regex' => 'Nomor WhatsApp harus berisi 9–15 digit dan boleh diawali tanda +.',
        ])->validate();

        $setting->forceFill(['value' => trim((string) $validated['value'])])->save();
        $this->logger->log(
            'site_setting.updated',
            'Pengaturan situs yang diizinkan diperbarui.',
            $actor,
            $setting,
            ['key' => $setting->key],
        );

        return $setting->refresh();
    }
}
