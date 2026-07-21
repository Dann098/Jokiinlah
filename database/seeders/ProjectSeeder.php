<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\ConsultationStatus;
use App\Enums\MilestoneStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Enums\RevisionPriority;
use App\Enums\RevisionStatus;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectMilestone;
use App\Models\Reminder;
use App\Models\Revision;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $staff = User::query()->where('email', 'staff@example.com')->firstOrFail();
        $staffDev = User::query()->where('email', 'staff.dev@example.com')->firstOrFail();
        $customers = User::query()->whereIn('email', ['customer@example.com', 'alya@example.com', 'bagas@example.com', 'citra@example.com'])->get()->keyBy('email');
        $services = Service::query()->get()->keyBy('slug');

        $consultation = Consultation::query()->updateOrCreate(['request_code' => 'CNS-20260722-0001'], [
            'user_id' => $customers['customer@example.com']->id,
            'service_id' => $services['pengembangan-website']->id,
            'name' => $customers['customer@example.com']->name,
            'email' => $customers['customer@example.com']->email,
            'phone' => $customers['customer@example.com']->phone,
            'project_title' => 'Portal Dokumentasi Penelitian',
            'description' => 'Portal untuk mengelola dokumen, milestone, dan review penelitian.',
            'deadline' => now()->addMonths(2),
            'technology' => 'Laravel dan MariaDB',
            'budget' => 8500000,
            'status' => ConsultationStatus::Converted,
            'privacy_accepted_at' => now()->subDays(10),
            'privacy_policy_version' => '1.0',
            'terms_version' => '1.0',
            'source' => 'website',
            'retention_until' => now()->addDays(180),
        ]);

        Consultation::query()->updateOrCreate(['request_code' => 'CNS-20260722-0002'], [
            'service_id' => $services['analisis-data-penelitian']->id,
            'name' => 'Rani Guest', 'email' => 'rani.guest@example.com', 'phone' => '+6281212345678',
            'project_title' => 'Analisis Data Kuesioner', 'description' => 'Memerlukan konsultasi metode analisis untuk data kuesioner.',
            'deadline' => now()->addWeeks(5), 'technology' => 'R atau SPSS', 'status' => ConsultationStatus::New,
            'privacy_accepted_at' => now(), 'privacy_policy_version' => '1.0', 'terms_version' => '1.0', 'source' => 'website', 'retention_until' => now()->addDays(180),
        ]);

        $projectData = [
            ['PRJ-20260722-0001', $customers['customer@example.com'], $staffDev, $services['pengembangan-website'], 'Portal Dokumentasi Penelitian', ProjectStatus::InProgress, 45, PaymentStatus::DownPayment, $consultation->id],
            ['PRJ-20260722-0002', $customers['alya@example.com'], $staff, $services['konsultasi-skripsi-penelitian'], 'Pendampingan Proposal Penelitian', ProjectStatus::WaitingData, 20, PaymentStatus::Unpaid, null],
            ['PRJ-20260722-0003', $customers['bagas@example.com'], $staff, $services['analisis-data-penelitian'], 'Analisis Regresi Data Survei', ProjectStatus::CustomerReview, 80, PaymentStatus::Paid, null],
            ['PRJ-20260722-0004', $customers['citra@example.com'], $staffDev, $services['dashboard-bisnis'], 'Dashboard Kinerja Operasional', ProjectStatus::Completed, 100, PaymentStatus::Paid, null],
        ];

        foreach ($projectData as [$code, $customer, $assigned, $service, $title, $status, $progress, $payment, $consultationId]) {
            $project = Project::query()->updateOrCreate(['project_code' => $code], [
                'consultation_id' => $consultationId,
                'customer_id' => $customer->id,
                'assigned_staff_id' => $assigned->id,
                'service_id' => $service->id,
                'title' => $title,
                'description' => 'Proyek demo realistis untuk memvalidasi alur, policy, status, milestone, dan hak akses.',
                'status' => $status,
                'progress' => $progress,
                'start_date' => now()->subWeeks(2),
                'deadline' => now()->addWeeks(4),
                'completed_at' => $status === ProjectStatus::Completed ? now()->subDay() : null,
                'payment_status' => $payment,
                'payment_note' => 'Status pembayaran diperbarui manual oleh admin.',
                'payment_updated_at' => now()->subDay(),
                'retention_until' => now()->addDays(180),
            ]);

            foreach (['Analisis Kebutuhan', 'Pengerjaan', 'Review dan Dokumentasi'] as $order => $milestone) {
                ProjectMilestone::query()->updateOrCreate(['project_id' => $project->id, 'title' => $milestone], [
                    'description' => 'Milestone berfungsi sebagai timeline dan tidak menghitung progress otomatis.',
                    'due_date' => now()->addDays(($order + 1) * 7),
                    'status' => $order === 0 ? MilestoneStatus::Completed : MilestoneStatus::Pending,
                    'completed_at' => $order === 0 ? now()->subDays(2) : null,
                    'sort_order' => $order + 1,
                ]);
            }

            Reminder::query()->updateOrCreate(['user_id' => $customer->id, 'project_id' => $project->id, 'title' => 'Review progres '.$title], ['description' => 'Periksa progres dan dokumen terbaru.', 'reminder_date' => now()->addDays(3), 'is_completed' => false]);
            Appointment::query()->updateOrCreate(['project_id' => $project->id, 'title' => 'Konsultasi '.$title], ['customer_id' => $customer->id, 'staff_id' => $assigned->id, 'appointment_date' => now()->addDays(5), 'meeting_link' => 'https://meet.google.com/demo-project', 'notes' => 'Tautan dan jadwal adalah data demonstrasi.', 'status' => AppointmentStatus::Scheduled]);
        }

        $primary = Project::query()->where('project_code', 'PRJ-20260722-0001')->firstOrFail();
        foreach ([1 => ['dokumen-kebutuhan.pdf', '11111111-1111-4111-8111-111111111111'], 2 => ['dokumen-kebutuhan-revisi.pdf', '22222222-2222-4222-8222-222222222222']] as $version => [$original, $stored]) {
            ProjectFile::withTrashed()->updateOrCreate(['project_id' => $primary->id, 'document_uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'version' => $version], [
                'uploaded_by' => $version === 1 ? $primary->customer_id : $primary->assigned_staff_id,
                'category' => 'dokumen_kebutuhan', 'original_name' => $original, 'stored_name' => $stored,
                'disk' => 'local', 'file_path' => 'private/projects/demo/'.$stored.'.pdf', 'file_type' => 'application/pdf',
                'file_size' => 120000 + ($version * 1000), 'checksum' => hash('sha256', $stored),
                'description' => 'Metadata file dummy. Berkas fisik tidak disertakan dalam repository.', 'retention_until' => now()->addDays(180),
            ]);
        }

        Revision::withTrashed()->updateOrCreate(['project_id' => $primary->id, 'title' => 'Penyesuaian alur persetujuan'], [
            'submitted_by' => $primary->customer_id, 'description' => 'Mohon tambahkan konfirmasi sebelum dokumen diselesaikan.',
            'section_reference' => 'Modul review', 'priority' => RevisionPriority::Normal, 'status' => RevisionStatus::UnderReview,
            'retention_until' => now()->addDays(180),
        ]);

        Reminder::query()->updateOrCreate(['user_id' => $admin->id, 'title' => 'Tinjau konsultasi baru'], ['description' => 'Periksa konsultasi guest yang belum terhubung.', 'reminder_date' => now()->addDay(), 'is_completed' => false]);
    }
}
