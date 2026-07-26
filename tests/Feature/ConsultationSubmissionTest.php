<?php

namespace Tests\Feature;

use App\Actions\Consultations\CreateConsultation;
use App\Models\Consultation;
use App\Models\Service;
use App\Models\User;
use App\Notifications\NewConsultationNotification;
use App\Services\CodeGenerator;
use App\Services\PrivateConsultationAttachment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ConsultationSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_guest_submission_is_stored_in_utc_without_creating_account(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $service = Service::factory()->create();
        $deadline = now('Asia/Jakarta')->addDays(2)->toDateString();

        $response = $this->post(route('consultations.store'), $this->validPayload($service, ['deadline' => $deadline]));

        $response->assertRedirect(route('contact.index'))->assertSessionHas('status')->assertSessionHas('consultation_code');
        $consultation = Consultation::query()->sole();
        $this->assertMatchesRegularExpression('/^CNS-[0-9]{8}-[0-9]{4}$/', $consultation->request_code);
        $expectedUtc = CarbonImmutable::parse($deadline, 'Asia/Jakarta')->endOfDay()->utc();
        $this->assertSame($expectedUtc->format('Y-m-d H:i:s'), $consultation->deadline->format('Y-m-d H:i:s'));
        $this->assertNull($consultation->user_id);
        $this->assertNotNull($consultation->privacy_accepted_at);
        $this->assertNotNull($consultation->academic_integrity_accepted_at);
        $this->assertSame(config('jokiinlah.privacy_policy_version'), $consultation->privacy_policy_version);
        $this->assertSame(config('jokiinlah.terms_version'), $consultation->terms_version);
        $this->assertDatabaseCount('users', 1);
        Notification::assertSentToTimes($admin, NewConsultationNotification::class, 2);
    }

    public function test_only_active_admins_receive_consultation_notifications(): void
    {
        Notification::fake();
        $activeAdmin = User::factory()->admin()->create();
        $inactiveAdmin = User::factory()->admin()->inactive()->create();
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create();
        $service = Service::factory()->create();

        $this->post(route('consultations.store'), $this->validPayload($service, [
            'email' => 'notification-scope@example.test',
        ]))->assertRedirect();

        Notification::assertSentToTimes($activeAdmin, NewConsultationNotification::class, 2);
        Notification::assertNotSentTo($inactiveAdmin, NewConsultationNotification::class);
        Notification::assertNotSentTo($staff, NewConsultationNotification::class);
        Notification::assertNotSentTo($customer, NewConsultationNotification::class);
    }

    public function test_mail_failure_does_not_cancel_consultation_or_database_notification(): void
    {
        config(['mail.default' => 'missing-mailer']);
        User::factory()->admin()->create();
        $service = Service::factory()->create();

        $this->post(route('consultations.store'), $this->validPayload($service, [
            'email' => 'mail-failure@example.test',
        ]))->assertRedirect(route('contact.index'))->assertSessionHas('consultation_code');

        $this->assertDatabaseCount('consultations', 1);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_submission_requires_privacy_and_academic_integrity_consent(): void
    {
        $service = Service::factory()->create();
        $payload = $this->validPayload($service);
        unset($payload['privacy'], $payload['academic_integrity']);

        $this->post(route('consultations.store'), $payload)
            ->assertSessionHasErrors(['privacy', 'academic_integrity']);
        $this->assertDatabaseCount('consultations', 0);
    }

    public function test_inactive_service_honeypot_and_past_deadline_are_rejected(): void
    {
        $inactive = Service::factory()->create(['is_active' => false]);
        $payload = $this->validPayload($inactive, ['website' => 'bot.example', 'deadline' => now('Asia/Jakarta')->subDay()->toDateString()]);

        $this->post(route('consultations.store'), $payload)
            ->assertSessionHasErrors(['service_id', 'website', 'deadline']);
        $this->assertDatabaseCount('consultations', 0);
    }

    public function test_valid_attachment_is_private_uuid_named_and_archives_are_not_extracted(): void
    {
        Storage::fake('local');
        $service = Service::factory()->create();
        $archive = UploadedFile::fake()->create('dokumen-awal.zip', 20, 'application/zip');

        $this->post(route('consultations.store'), $this->validPayload($service, ['attachment' => $archive]))->assertRedirect();

        $consultation = Consultation::query()->sole();
        Storage::disk('local')->assertExists($consultation->attachment_path);
        $this->assertSame('dokumen-awal.zip', $consultation->attachment_original_name);
        $this->assertMatchesRegularExpression('/^consultations\/[0-9a-f-]{36}\/[0-9a-f-]{36}$/', $consultation->attachment_path);
        $this->assertSame(64, strlen((string) $consultation->attachment_checksum));
        $this->assertCount(1, Storage::disk('local')->allFiles('consultations'));
    }

    public function test_executable_and_oversized_attachments_are_rejected(): void
    {
        Storage::fake('local');
        $service = Service::factory()->create();

        $this->post(route('consultations.store'), $this->validPayload($service, [
            'attachment' => UploadedFile::fake()->create('payload.php', 2, 'application/x-httpd-php'),
        ]))->assertSessionHasErrors('attachment');

        $this->post(route('consultations.store'), $this->validPayload($service, [
            'email' => 'besar@example.test',
            'attachment' => UploadedFile::fake()->create('besar.pdf', 20481, 'application/pdf'),
        ]))->assertSessionHasErrors('attachment');

        $this->assertDatabaseCount('consultations', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('consultations'));
    }

    public function test_mime_mismatch_missing_extension_and_dangerous_double_extension_are_rejected(): void
    {
        Storage::fake('local');
        $service = Service::factory()->create();

        foreach ([
            UploadedFile::fake()->create('dokumen.pdf', 10, 'text/plain'),
            UploadedFile::fake()->create('dokumen', 10, 'application/pdf'),
            UploadedFile::fake()->create('dokumen.php.pdf', 10, 'application/pdf'),
        ] as $index => $file) {
            $this->post(route('consultations.store'), $this->validPayload($service, [
                'email' => 'lampiran-'.$index.'@example.test',
                'attachment' => $file,
            ]))->assertSessionHasErrors('attachment');
        }

        $this->assertDatabaseCount('consultations', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('consultations'));
    }

    public function test_validation_keeps_safe_input_and_links_error_to_field(): void
    {
        $service = Service::factory()->create();
        $response = $this->from(route('contact.index'))->post(route('consultations.store'), $this->validPayload($service, [
            'name' => '',
            'description' => 'terlalu singkat',
            'technology' => 'Laravel dan Alpine.js',
        ]));

        $response->assertRedirect(route('contact.index'))->assertSessionHasErrors(['name', 'description']);
        $page = $this->get(route('contact.index'))->assertOk();
        $page->assertSee('value=\'Laravel dan Alpine.js\'', false)
            ->assertSee('Nama lengkap wajib diisi.')
            ->assertSee('aria-describedby=\'name-error\'', false)
            ->assertSee('aria-describedby=\'description-hint description-error\'', false)
            ->assertSee('data-error-summary', false)
            ->assertSee('tabindex=\'-1\'', false)
            ->assertSee('id=\'name-error\'', false);
    }

    public function test_attachment_original_filename_is_sanitized(): void
    {
        Storage::fake('local');
        $service = Service::factory()->create();
        $file = UploadedFile::fake()->create('../brief'.chr(13).chr(10).'injeksi.pdf', 10, 'application/pdf');

        $this->post(route('consultations.store'), $this->validPayload($service, ['attachment' => $file]))->assertRedirect();

        $consultation = Consultation::query()->sole();
        $this->assertStringNotContainsString('..', $consultation->attachment_original_name);
        $this->assertStringNotContainsString(chr(13), $consultation->attachment_original_name);
        $this->assertStringNotContainsString(chr(10), $consultation->attachment_original_name);
        $this->assertStringNotContainsString('brief', $consultation->attachment_path);
    }

    public function test_storage_is_cleaned_when_database_creation_fails(): void
    {
        Storage::fake('local');
        $service = Service::factory()->create();
        $codes = Mockery::mock(CodeGenerator::class);
        $codes->shouldReceive('generate')->once()->andThrow(new RuntimeException('forced failure'));
        $this->app->instance(CodeGenerator::class, $codes);

        try {
            $this->post(route('consultations.store'), $this->validPayload($service, [
                'attachment' => UploadedFile::fake()->create('brief.pdf', 10, 'application/pdf'),
            ]));
        } catch (RuntimeException $exception) {
            $this->assertSame('forced failure', $exception->getMessage());
        }

        $this->assertDatabaseCount('consultations', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('consultations'));
    }

    public function test_duplicate_submission_returns_same_code_and_does_not_notify_twice(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $service = Service::factory()->create();
        $payload = $this->validPayload($service);

        $first = $this->post(route('consultations.store'), $payload);
        $second = $this->post(route('consultations.store'), $payload);

        $this->assertDatabaseCount('consultations', 1);
        $this->assertSame($first->getSession()->get('consultation_code'), $second->getSession()->get('consultation_code'));
        Notification::assertSentToTimes($admin, NewConsultationNotification::class, 2);
    }

    public function test_duplicate_submission_does_not_store_a_second_attachment(): void
    {
        Storage::fake('local');
        $service = Service::factory()->create();
        $payload = $this->validPayload($service);

        $this->post(route('consultations.store'), array_merge($payload, [
            'attachment' => UploadedFile::fake()->create('pertama.pdf', 10, 'application/pdf'),
        ]))->assertRedirect();
        $this->post(route('consultations.store'), array_merge($payload, [
            'attachment' => UploadedFile::fake()->create('kedua.pdf', 10, 'application/pdf'),
        ]))->assertRedirect();

        $this->assertDatabaseCount('consultations', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles('consultations'));
    }

    public function test_public_payload_cannot_override_internal_consultation_fields(): void
    {
        Notification::fake();
        $service = Service::factory()->create();
        $attacker = User::factory()->customer()->create();

        $this->post(route('consultations.store'), $this->validPayload($service, [
            'user_id' => $attacker->id,
            'request_code' => 'CNS-FORCED',
            'status' => 'converted',
            'source' => 'forged',
            'submission_fingerprint' => str_repeat('a', 64),
            'privacy_policy_version' => 'forged',
            'terms_version' => 'forged',
        ]))->assertRedirect();

        $consultation = Consultation::query()->sole();
        $this->assertNull($consultation->user_id);
        $this->assertNotSame('CNS-FORCED', $consultation->request_code);
        $this->assertSame('new', $consultation->status->value);
        $this->assertSame('public_website', $consultation->source);
        $this->assertSame(config('jokiinlah.privacy_policy_version'), $consultation->privacy_policy_version);
        $this->assertSame(config('jokiinlah.terms_version'), $consultation->terms_version);
    }

    public function test_request_code_skips_existing_seeded_sequence_numbers(): void
    {
        Notification::fake();
        $service = Service::factory()->create();
        $date = now('Asia/Jakarta')->format('Ymd');
        Consultation::factory()->for($service)->create(['request_code' => 'CNS-'.$date.'-0001']);
        Consultation::factory()->for($service)->create(['request_code' => 'CNS-'.$date.'-0002']);

        $this->post(route('consultations.store'), $this->validPayload($service))->assertRedirect();

        $this->assertDatabaseHas('consultations', ['request_code' => 'CNS-'.$date.'-0003']);
    }

    public function test_public_consultation_rate_limit_is_enforced(): void
    {
        Notification::fake();
        $service = Service::factory()->create();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('consultations.store'), $this->validPayload($service, ['project_title' => 'Proyek '.$attempt]))->assertRedirect();
        }

        $this->post(route('consultations.store'), $this->validPayload($service, ['project_title' => 'Proyek keenam']))
            ->assertTooManyRequests();
    }

    public function test_public_consultation_ip_rate_limit_is_enforced_across_identities(): void
    {
        Notification::fake();
        $service = Service::factory()->create();
        $client = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.42']);

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $client->post(route('consultations.store'), $this->validPayload($service, [
                'email' => 'ip-rate-'.$attempt.'@example.test',
                'phone' => '08123456'.str_pad((string) $attempt, 4, '0', STR_PAD_LEFT),
                'project_title' => 'Proyek IP '.$attempt,
            ]))->assertRedirect();
        }

        $client->post(route('consultations.store'), $this->validPayload($service, [
            'email' => 'ip-rate-11@example.test',
            'phone' => '081234560011',
            'project_title' => 'Proyek IP kesebelas',
        ]))->assertTooManyRequests();
    }

    public function test_phone_format_variations_cannot_bypass_identity_rate_limit(): void
    {
        Notification::fake();
        $service = Service::factory()->create();
        $formats = ['0812-3456-7890', '6281234567890', '+62 812 3456 7890', '81234567890', '081234567890'];

        foreach ($formats as $index => $phone) {
            $this->post(route('consultations.store'), $this->validPayload($service, [
                'email' => 'rate-normalized@example.test',
                'phone' => $phone,
                'project_title' => 'Proyek format '.($index + 1),
            ]))->assertRedirect();
        }

        $this->post(route('consultations.store'), $this->validPayload($service, [
            'email' => 'rate-normalized@example.test',
            'phone' => '+62 812-3456-7890',
            'project_title' => 'Proyek format keenam',
        ]))->assertTooManyRequests()->assertSee('Terlalu banyak permintaan konsultasi');
    }

    public function test_storage_failure_does_not_create_consultation_record(): void
    {
        $service = Service::factory()->create();
        $attachments = Mockery::mock(PrivateConsultationAttachment::class);
        $attachments->shouldReceive('store')->once()->andThrow(new RuntimeException('forced storage failure'));
        $this->app->instance(PrivateConsultationAttachment::class, $attachments);
        $exception = null;

        try {
            app(CreateConsultation::class)->execute([
                'name' => 'Nadia Pratama',
                'email' => 'storage-failure@example.test',
                'phone' => '6281234567890',
                'service_id' => $service->id,
                'project_title' => 'Uji kegagalan storage',
                'description' => 'Deskripsi valid untuk memastikan action mencapai proses penyimpanan attachment.',
                'technology' => null,
                'budget' => null,
            ], UploadedFile::fake()->create('brief.pdf', 10, 'application/pdf'));
        } catch (RuntimeException $caught) {
            $exception = $caught;
        }

        $this->assertNotNull($exception);
        $this->assertSame('forced storage failure', $exception->getMessage());
        $this->assertDatabaseCount('consultations', 0);
    }

    private function validPayload(Service $service, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Nadia Pratama',
            'email' => 'nadia@example.test',
            'phone' => '081234567890',
            'service_id' => $service->id,
            'project_title' => 'Dashboard analisis penelitian',
            'description' => 'Saya memerlukan konsultasi untuk menyusun alur analisis dan dashboard penelitian yang aman.',
            'technology' => 'Laravel',
            'budget' => 5000000,
            'privacy' => '1',
            'academic_integrity' => '1',
            'website' => null,
        ], $overrides);
    }
}
