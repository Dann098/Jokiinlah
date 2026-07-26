<?php

namespace App\Http\Controllers\Public;

use App\Actions\Consultations\CreateConsultation;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConsultationRequest;
use App\Models\User;
use App\Notifications\NewConsultationNotification;
use App\Services\WhatsAppUrlBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class ConsultationController extends Controller
{
    public function store(StoreConsultationRequest $request, CreateConsultation $action, WhatsAppUrlBuilder $whatsApp): RedirectResponse
    {
        $consultation = $action->execute($request->validated(), $request->file('attachment'));

        if ($consultation->wasRecentlyCreated) {
            User::query()->active()->where('role', UserRole::Admin->value)->each(function (User $admin) use ($consultation): void {
                try {
                    $admin->notify(new NewConsultationNotification($consultation, ['database']));
                } catch (Throwable $exception) {
                    Log::warning('Database notification konsultasi gagal.', ['consultation_id' => $consultation->id, 'exception' => $exception::class]);
                }
                try {
                    $admin->notify(new NewConsultationNotification($consultation, ['mail']));
                } catch (Throwable $exception) {
                    Log::warning('Email notification konsultasi gagal.', ['consultation_id' => $consultation->id, 'exception' => $exception::class]);
                }
            });
        }

        return redirect()->route('contact.index')->with([
            'status' => 'Permintaan konsultasi Anda telah diterima.',
            'consultation_code' => $consultation->request_code,
            'consultation_whatsapp' => $whatsApp->build('Halo, saya telah mengirim permintaan konsultasi dengan kode '.$consultation->request_code.'. Saya ingin melanjutkan konsultasi.'),
        ]);
    }
}
