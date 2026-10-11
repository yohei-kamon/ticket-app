<?php

use App\Models\Event;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('イベント管理')] class extends Component {
    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->authorize('create', Event::class);
    }

    /**
     * Get the events organized by the current user.
     *
     * @return Collection<int, Event>
     */
    #[Computed]
    public function events(): Collection
    {
        return Auth::user()->events()->orderBy('starts_at')->get();
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <flux:heading size="xl" level="1">イベント管理</flux:heading>

        <flux:button variant="primary" icon="plus" :href="route('organizer.events.create')" wire:navigate>
            イベントを登録
        </flux:button>
    </div>

    @if ($this->events->isEmpty())
        <flux:text>まだイベントがありません。「イベントを登録」から作成してください。</flux:text>
    @else
        <div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @foreach ($this->events as $event)
                <div class="flex flex-wrap items-center gap-4 p-4" wire:key="event-{{ $event->id }}">
                    <div class="min-w-0 flex-1">
                        <flux:heading class="truncate">{{ $event->title }}</flux:heading>
                        <flux:text class="mt-1">{{ $event->starts_at->isoFormat('M月D日(ddd) HH:mm') }}</flux:text>
                    </div>

                    <flux:badge :color="$event->status->badgeColor()" size="sm">
                        {{ $event->status->label() }}
                    </flux:badge>

                    <flux:button size="sm" icon="eye" :href="route('events.show', $event)" wire:navigate>
                        詳細
                    </flux:button>

                    <flux:button size="sm" icon="pencil-square" :href="route('organizer.events.edit', $event)" wire:navigate>
                        編集
                    </flux:button>
                </div>
            @endforeach
        </div>
    @endif
</section>
