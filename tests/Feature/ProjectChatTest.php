<?php

namespace Tests\Feature;

use App\Actions\Projects\MarkProjectChatRead;
use App\Actions\Projects\SendProjectMessage;
use App\Enums\ProjectStatus;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Projects\RelationManagers\MessagesRelationManager;
use App\Livewire\ProjectChat;
use App\Models\Project;
use App\Models\ProjectChatParticipant;
use App\Models\ProjectMessage;
use App\Models\User;
use App\Notifications\ProjectMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class ProjectChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_message_relationships_are_defined(): void
    {
        [$project, $customer] = $this->projectContext();
        $message = $this->send($project, $customer, 'Pesan relasi');

        $this->assertTrue($message->project->is($project));
        $this->assertTrue($message->sender->is($customer));
        $this->assertTrue($project->messages->contains($message));
    }

    public function test_user_has_sent_project_messages_relationship(): void
    {
        [$project, $customer] = $this->projectContext();
        $message = $this->send($project, $customer, 'Pesan milik user');

        $this->assertTrue($customer->sentProjectMessages->contains($message));
    }

    public function test_owner_customer_can_view_and_send_project_chat(): void
    {
        [$project, $customer] = $this->projectContext();

        $this->assertTrue(Gate::forUser($customer)->allows('viewChat', $project));
        $this->assertTrue(Gate::forUser($customer)->allows('sendMessage', $project));
    }

    public function test_foreign_customer_cannot_view_or_send_project_chat(): void
    {
        [$project] = $this->projectContext();
        $foreign = User::factory()->customer()->create();

        $this->assertFalse(Gate::forUser($foreign)->allows('viewChat', $project));
        $this->assertFalse(Gate::forUser($foreign)->allows('sendMessage', $project));
    }

    public function test_assigned_staff_can_view_and_send_project_chat(): void
    {
        [$project, , $staff] = $this->projectContext();

        $this->assertTrue(Gate::forUser($staff)->allows('viewChat', $project));
        $this->assertTrue(Gate::forUser($staff)->allows('sendMessage', $project));
    }

    public function test_unassigned_staff_cannot_view_or_send_project_chat(): void
    {
        [$project] = $this->projectContext();
        $staff = User::factory()->staff()->create();

        $this->assertFalse(Gate::forUser($staff)->allows('viewChat', $project));
        $this->assertFalse(Gate::forUser($staff)->allows('sendMessage', $project));
    }

    public function test_admin_can_view_and_send_every_project_chat(): void
    {
        [$project] = $this->projectContext();
        $admin = User::factory()->admin()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('viewChat', $project));
        $this->assertTrue(Gate::forUser($admin)->allows('sendMessage', $project));
    }

    public function test_customer_can_send_before_staff_is_assigned(): void
    {
        [$project, $customer] = $this->projectContext(null);

        $message = $this->send($project, $customer, 'Mohon segera tentukan pendamping.');

        $this->assertSame($customer->id, $message->sender_id);
    }

    public function test_cancelled_project_chat_is_readable_but_read_only(): void
    {
        [$project, $customer] = $this->projectContext(status: ProjectStatus::Cancelled);

        $this->assertTrue(Gate::forUser($customer)->allows('viewChat', $project));
        $this->assertFalse(Gate::forUser($customer)->allows('sendMessage', $project));
    }

    public function test_completed_project_chat_remains_sendable(): void
    {
        [$project, $customer] = $this->projectContext(status: ProjectStatus::Completed);

        $this->assertTrue(Gate::forUser($customer)->allows('sendMessage', $project));
    }

    public function test_soft_deleted_project_chat_is_inaccessible(): void
    {
        [$project, $customer] = $this->projectContext();
        $project->delete();

        $this->assertFalse(Gate::forUser($customer)->allows('viewChat', $project));
        $this->assertFalse(Gate::forUser($customer)->allows('sendMessage', $project));
    }

    public function test_message_is_trimmed_and_limited_to_two_thousand_characters(): void
    {
        [$project, $customer] = $this->projectContext();

        $message = $this->send($project, $customer, '  Pesan aman  ');
        $this->assertSame('Pesan aman', $message->message);

        $this->expectException(ValidationException::class);
        $this->send($project, $customer, str_repeat('x', 2001));
    }

    public function test_blank_message_is_rejected(): void
    {
        [$project, $customer] = $this->projectContext();

        $this->expectException(ValidationException::class);
        $this->send($project, $customer, '   ');
    }

    public function test_message_rate_limit_is_twenty_per_minute_per_user_and_project(): void
    {
        Notification::fake();
        [$project, $customer] = $this->projectContext();

        foreach (range(1, 20) as $number) {
            $this->send($project, $customer, 'Pesan '.$number);
        }

        $this->expectException(ValidationException::class);
        $this->send($project, $customer, 'Pesan ke-21');
    }

    public function test_rate_limit_is_independent_between_projects(): void
    {
        Notification::fake();
        [$project, $customer] = $this->projectContext();
        $other = Project::factory()->create(['customer_id' => $customer->id]);

        foreach (range(1, 20) as $number) {
            $this->send($project, $customer, 'Pesan '.$number);
        }

        $this->assertInstanceOf(ProjectMessage::class, $this->send($other, $customer, 'Proyek lain'));
    }

    public function test_messages_cannot_be_updated(): void
    {
        [$project, $customer] = $this->projectContext();
        $message = $this->send($project, $customer, 'Pesan permanen');

        $this->expectException(LogicException::class);
        $message->update(['message' => 'Diubah']);
    }

    public function test_messages_cannot_be_deleted_normally(): void
    {
        [$project, $customer] = $this->projectContext();
        $message = $this->send($project, $customer, 'Pesan permanen');

        $this->expectException(LogicException::class);
        $message->delete();
    }

    public function test_opening_chat_marks_participant_read_at_latest_message(): void
    {
        [$project, $customer, $staff] = $this->projectContext();
        $message = $this->send($project, $staff, 'Pesan untuk customer');

        app(MarkProjectChatRead::class)->execute($project, $customer);

        $participant = ProjectChatParticipant::query()
            ->whereBelongsTo($project)
            ->where('user_id', $customer->id)
            ->sole();
        $this->assertTrue($participant->last_read_at->greaterThanOrEqualTo($message->created_at));
    }

    public function test_unread_count_excludes_own_messages(): void
    {
        [$project, $customer, $staff] = $this->projectContext();
        $this->send($project, $customer, 'Pesan sendiri');
        $this->send($project, $staff, 'Pesan staff');

        $this->assertSame(1, $project->unreadMessagesFor($customer));
    }

    public function test_unread_count_only_counts_messages_after_last_read(): void
    {
        [$project, $customer, $staff] = $this->projectContext();
        $this->send($project, $staff, 'Sudah dibaca');
        app(MarkProjectChatRead::class)->execute($project, $customer);
        $this->travel(1)->second();
        $this->send($project, $staff, 'Belum dibaca');

        $this->assertSame(1, $project->unreadMessagesFor($customer));
    }

    public function test_customer_message_notifies_assigned_staff_and_active_admins(): void
    {
        Notification::fake();
        [$project, $customer, $staff] = $this->projectContext();
        $admin = User::factory()->admin()->create();

        $this->send($project, $customer, 'Mohon cek data terbaru.');

        Notification::assertSentTo($staff, ProjectMessageNotification::class);
        Notification::assertSentTo($admin, ProjectMessageNotification::class);
    }

    public function test_message_does_not_notify_sender_or_unassigned_staff(): void
    {
        Notification::fake();
        [$project, $customer] = $this->projectContext();
        $otherStaff = User::factory()->staff()->create();

        $this->send($project, $customer, 'Mohon cek data terbaru.');

        Notification::assertNotSentTo($customer, ProjectMessageNotification::class);
        Notification::assertNotSentTo($otherStaff, ProjectMessageNotification::class);
    }

    public function test_staff_message_notifies_customer_and_admin(): void
    {
        Notification::fake();
        [$project, $customer, $staff] = $this->projectContext();
        $admin = User::factory()->admin()->create();

        $this->send($project, $staff, 'Progress sudah diperbarui.');

        Notification::assertSentTo($customer, ProjectMessageNotification::class);
        Notification::assertSentTo($admin, ProjectMessageNotification::class);
        Notification::assertNotSentTo($staff, ProjectMessageNotification::class);
    }

    public function test_message_notification_contains_safe_snippet_and_context_url(): void
    {
        [$project, $customer, $staff] = $this->projectContext();
        $message = $this->send($project, $customer, str_repeat('a', 120));
        $payload = (new ProjectMessageNotification($message, ['database']))->toArray($staff);

        $this->assertSame(100, mb_strlen($payload['snippet']));
        $this->assertStringContainsString((string) $project->id, $payload['url']);
        $this->assertSame($project->project_code, $payload['project_code']);
    }

    public function test_activity_log_records_message_metadata_without_message_content(): void
    {
        [$project, $customer] = $this->projectContext();
        $sensitiveMessage = 'Isi pesan yang tidak boleh masuk audit log';
        $message = $this->send($project, $customer, $sensitiveMessage);
        $log = $message->activityLogs()->where('action', 'project.message_sent')->sole();

        $encoded = json_encode($log->toArray());
        $this->assertStringNotContainsString($sensitiveMessage, $encoded);
        $this->assertSame($message->id, $log->metadata['message_id']);
    }

    public function test_livewire_chat_renders_plain_escaped_messages_and_semantics(): void
    {
        [$project, $customer] = $this->projectContext();
        $this->send($project, $customer, '<script>alert(1)</script>');

        Livewire::actingAs($customer)
            ->test(ProjectChat::class, ['project' => $project])
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('aria-live="polite"', false)
            ->assertSee('<ol', false);
    }

    public function test_livewire_chat_initially_loads_latest_forty_messages(): void
    {
        Notification::fake();
        [$project, $customer] = $this->projectContext();
        foreach (range(1, 45) as $number) {
            ProjectMessage::query()->forceCreate([
                'project_id' => $project->id,
                'sender_id' => $customer->id,
                'message' => $number === 1 ? 'Pesan paling lama' : 'Urutan-'.$number,
            ]);
        }

        Livewire::actingAs($customer)
            ->test(ProjectChat::class, ['project' => $project])
            ->assertDontSee('Pesan paling lama')
            ->assertSee('Urutan-45');
    }

    public function test_livewire_chat_can_load_older_messages(): void
    {
        [$project, $customer] = $this->projectContext();
        foreach (range(1, 45) as $number) {
            ProjectMessage::query()->forceCreate([
                'project_id' => $project->id,
                'sender_id' => $customer->id,
                'message' => 'Riwayat-'.$number,
            ]);
        }

        Livewire::actingAs($customer)
            ->test(ProjectChat::class, ['project' => $project])
            ->call('loadOlder')
            ->assertSee('Riwayat-1');
    }

    public function test_livewire_send_validates_and_clears_input(): void
    {
        Notification::fake();
        [$project, $customer] = $this->projectContext();

        Livewire::actingAs($customer)
            ->test(ProjectChat::class, ['project' => $project])
            ->set('message', 'Pesan dari komponen')
            ->call('send')
            ->assertSet('message', '');

        $this->assertDatabaseHas('project_messages', ['message' => 'Pesan dari komponen']);
    }

    public function test_project_resource_exposes_scoped_message_relation_manager(): void
    {
        $this->assertContains(MessagesRelationManager::class, ProjectResource::getRelations());
    }

    public function test_chat_has_no_global_attachment_edit_or_delete_routes(): void
    {
        $routeNames = collect(Route::getRoutes())->map(fn ($route) => $route->getName())->filter();

        $this->assertFalse($routeNames->contains('project-chat.global'));
        $this->assertFalse($routeNames->contains('project-messages.attachments.store'));
        $this->assertFalse($routeNames->contains('project-messages.update'));
        $this->assertFalse($routeNames->contains('project-messages.destroy'));
    }

    private function send(Project $project, User $sender, string $message): ProjectMessage
    {
        return app(SendProjectMessage::class)->execute($project, $sender, $message);
    }

    /**
     * @return array{Project, User, User|null}
     */
    private function projectContext(
        User|false|null $staff = false,
        ProjectStatus $status = ProjectStatus::InProgress,
    ): array {
        $customer = User::factory()->customer()->create();
        $assignedStaff = $staff === false ? User::factory()->staff()->create() : $staff;
        $project = Project::factory()->create([
            'customer_id' => $customer->id,
            'assigned_staff_id' => $assignedStaff?->id,
            'status' => $status,
        ]);

        return [$project, $customer, $assignedStaff];
    }
}
