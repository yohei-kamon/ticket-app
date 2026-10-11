<?php

use App\Enums\EventStatus;
use App\Models\Event;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('イベント編集')] class extends Component {
    public Event $event;

    public string $title = '';
    public string $description = '';
    public string $venue = '';
    public string $starts_at = '';
    public string $ends_at = '';

    /**
     * Get the validation rules for the event.
     *
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string'],
            'venue' => ['required', 'string', 'max:100'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ];
    }

    /**
     * Mount the component.
     */
    public function mount(Event $event): void
    {
        $this->authorize('update', $event);

        $this->event = $event;
        $this->title = $event->title;
        $this->description = $event->description;
        $this->venue = $event->venue;
        $this->starts_at = $event->starts_at->format('Y-m-d\TH:i');
        $this->ends_at = $event->ends_at->format('Y-m-d\TH:i');
    }

    /**
     * Update the event.
     */
    public function save(): void
    {
        $this->authorize('update', $this->event);

        $validated = $this->validate();

        $this->event->update($validated);

        Flux::toast(variant: 'success', text: 'イベントを保存しました。');
    }

    /**
     * Toggle the event between published and draft.
     */
    public function togglePublish(): void
    {
        $this->authorize('update', $this->event);
        $this->authorize('publish', $this->event);

        $this->event->status = $this->event->isPublished()
            ? EventStatus::Draft
            : EventStatus::Published;
        $this->event->save();

        Flux::toast(variant: 'success', text: "イベントを「{$this->event->status->label()}」にしました。");
    }

    /**
     * Delete the event.
     */
    public function deleteEvent(): void
    {
        $this->authorize('delete', $this->event);

        $this->event->delete();

        $this->redirectRoute('organizer.events.index', navigate: true);
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <flux:heading size="xl" level="1">イベント編集</flux:heading>
            <flux:badge :color="$event->status->badgeColor()" size="sm">{{ $event->status->label() }}</flux:badge>
        </div>

        @can('publish', $event)
            <flux:button wire:click="togglePublish" :variant="$event->isPublished() ? 'filled' : 'primary'">
                {{ $event->isPublished() ? '下書きに戻す' : '公開する' }}
            </flux:button>
        @endcan
    </div>

    @cannot('publish', $event)
        <flux:callout variant="warning" icon="exclamation-triangle" heading="管理者により非公開にされています">
            <flux:callout.text>このイベントは公開できません。内容の編集と削除はできます。</flux:callout.text>
        </flux:callout>
    @endcannot

    <form wire:submit="save" class="space-y-6">
        @include('partials.event-form-fields')

        <div class="flex items-center gap-4">
            <flux:button variant="primary" type="submit">保存</flux:button>
            <flux:button variant="ghost" :href="route('organizer.events.index')" wire:navigate>一覧に戻る</flux:button>
        </div>
    </form>

    <flux:separator />

    <div class="space-y-3">
        <flux:heading>イベントの削除</flux:heading>
        <flux:text>削除すると元に戻せません。</flux:text>
        <flux:button variant="danger" wire:click="deleteEvent" wire:confirm="このイベントを削除しますか？">
            削除する
        </flux:button>
    </div>
</section>
