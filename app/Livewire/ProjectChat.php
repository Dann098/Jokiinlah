<?php

namespace App\Livewire;

use App\Actions\Projects\MarkProjectChatRead;
use App\Actions\Projects\SendProjectMessage;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class ProjectChat extends Component
{
    use AuthorizesRequests;

    public Project $project;

    public string $message = '';

    public int $limit = 40;

    public function mount(Project $project): void
    {
        $this->authorize('viewChat', $project);
        $this->project = $project;
        app(MarkProjectChatRead::class)->execute($project, auth()->user());
    }

    public function send(SendProjectMessage $send): void
    {
        $this->authorize('sendMessage', $this->project);
        $send->execute($this->project, auth()->user(), $this->message);
        $this->message = '';
        app(MarkProjectChatRead::class)->execute($this->project, auth()->user());
    }

    public function refreshMessages(MarkProjectChatRead $read): void
    {
        $this->authorize('viewChat', $this->project);
        $read->execute($this->project, auth()->user());
    }

    public function loadOlder(): void
    {
        $this->authorize('viewChat', $this->project);
        $this->limit += 40;
    }

    public function render(): View
    {
        $messages = $this->project->messages()
            ->with('sender:id,name')
            ->latest('id')
            ->limit($this->limit)
            ->get()
            ->reverse()
            ->values();

        return view('livewire.project-chat', [
            'messages' => $messages,
            'hasOlder' => $this->project->messages()->count() > $this->limit,
            'canSend' => auth()->user()->can('sendMessage', $this->project),
        ]);
    }
}
