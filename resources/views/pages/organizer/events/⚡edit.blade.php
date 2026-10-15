<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\TicketType;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('イベント編集')] class extends Component {
    public Event $event;

    public string $title = '';
    public string $description = '';
    public string $venue = '';
    public string $starts_at = '';
    public string $ends_at = '';

    public ?int $editingTicketTypeId = null;
    public string $ticketTypeName = '';
    public int|string $ticketTypePrice = '';
    public int|string $ticketTypeCapacity = '';
    public string $ticketTypeSalesStartsAt = '';
    public string $ticketTypeSalesEndsAt = '';

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

        $this->resetTicketTypeForm();
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

        if (! $this->event->isPublished() && ! $this->event->ticketTypes()->exists()) {
            Flux::toast(variant: 'danger', text: '券種を1つ以上登録すると公開できます。');

            return;
        }

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

        if ($this->event->orders()->exists()) {
            Flux::toast(variant: 'danger', text: '注文があるため、このイベントは削除できません。');

            return;
        }

        $this->event->delete();

        $this->redirectRoute('organizer.events.index', navigate: true);
    }
    /**
     * Get the ticket types of the event.
     *
     * @return Collection<int, TicketType>
     */
    #[Computed]
    public function ticketTypes(): Collection
    {
        return $this->event->ticketTypes()->orderBy('id')->get();
    }

    /**
     * Store a new ticket type or update the one being edited.
     */
    public function saveTicketType(): void
    {
        $this->authorize('update', $this->event);

        $validated = $this->validate([
            'ticketTypeName' => ['required', 'string', 'max:50'],
            'ticketTypePrice' => ['required', 'integer', 'min:0'],
            'ticketTypeCapacity' => ['required', 'integer', 'min:1'],
            'ticketTypeSalesStartsAt' => ['required', 'date'],
            'ticketTypeSalesEndsAt' => ['required', 'date', 'after:ticketTypeSalesStartsAt'],
        ], [], [
            'ticketTypeName' => '券種名',
            'ticketTypePrice' => '価格',
            'ticketTypeCapacity' => '定員',
            'ticketTypeSalesStartsAt' => '販売開始日時',
            'ticketTypeSalesEndsAt' => '販売終了日時',
        ]);

        $attributes = [
            'name' => $validated['ticketTypeName'],
            'price' => (int) $validated['ticketTypePrice'],
            'capacity' => (int) $validated['ticketTypeCapacity'],
            'sales_starts_at' => $validated['ticketTypeSalesStartsAt'],
            'sales_ends_at' => $validated['ticketTypeSalesEndsAt'],
        ];

        if ($this->editingTicketTypeId !== null) {
            $this->event->ticketTypes()->findOrFail($this->editingTicketTypeId)->update($attributes);
            $message = '券種を更新しました。';
        } else {
            $this->event->ticketTypes()->create($attributes);
            $message = '券種を追加しました。';
        }

        $this->resetTicketTypeForm();

        Flux::toast(variant: 'success', text: $message);
    }

    /**
     * Fill the form with the ticket type to edit.
     */
    public function editTicketType(int $ticketTypeId): void
    {
        $this->authorize('update', $this->event);

        $ticketType = $this->event->ticketTypes()->findOrFail($ticketTypeId);

        $this->resetValidation();
        $this->editingTicketTypeId = $ticketType->id;
        $this->ticketTypeName = $ticketType->name;
        $this->ticketTypePrice = $ticketType->price;
        $this->ticketTypeCapacity = $ticketType->capacity;
        $this->ticketTypeSalesStartsAt = $ticketType->sales_starts_at->format('Y-m-d\TH:i');
        $this->ticketTypeSalesEndsAt = $ticketType->sales_ends_at->format('Y-m-d\TH:i');
    }

    /**
     * Stop editing and clear the ticket type form.
     */
    public function cancelEditTicketType(): void
    {
        $this->authorize('update', $this->event);

        $this->resetTicketTypeForm();
    }

    /**
     * Delete the ticket type.
     */
    public function deleteTicketType(int $ticketTypeId): void
    {
        $this->authorize('update', $this->event);

        $ticketType = $this->event->ticketTypes()->findOrFail($ticketTypeId);

        if ($ticketType->orderItems()->exists()) {
            Flux::toast(variant: 'danger', text: '注文があるため、この券種は削除できません。');

            return;
        }

        if ($this->event->isPublished() && $this->event->ticketTypes()->count() === 1) {
            Flux::toast(variant: 'danger', text: '公開中のイベントは券種を0にできません。先に下書きに戻してください。');

            return;
        }

        $ticketType->delete();

        if ($this->editingTicketTypeId === $ticketType->id) {
            $this->resetTicketTypeForm();
        }

        Flux::toast(variant: 'success', text: '券種を削除しました。');
    }

    /**
     * Clear the ticket type form, defaulting the sales end to the event start.
     */
    private function resetTicketTypeForm(): void
    {
        $this->resetValidation();
        $this->editingTicketTypeId = null;
        $this->ticketTypeName = '';
        $this->ticketTypePrice = '';
        $this->ticketTypeCapacity = '';
        $this->ticketTypeSalesStartsAt = '';
        $this->ticketTypeSalesEndsAt = $this->event->starts_at->format('Y-m-d\TH:i');
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <flux:heading size="xl" level="1">イベント編集</flux:heading>
            <flux:badge :color="$event->status->badgeColor()" size="sm">{{ $event->status->label() }}</flux:badge>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:button icon="eye" :href="route('events.show', $event)" wire:navigate>
                詳細を見る
            </flux:button>

            @can('publish', $event)
                <flux:button
                    wire:click="togglePublish"
                    :variant="$event->isPublished() ? 'filled' : 'primary'"
                    :disabled="! $event->isPublished() && $this->ticketTypes->isEmpty()"
                >
                    {{ $event->isPublished() ? '下書きに戻す' : '公開する' }}
                </flux:button>
            @endcan
        </div>
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

    <div class="space-y-6">
        <flux:heading size="lg" level="2">券種</flux:heading>

        @if ($this->ticketTypes->isEmpty())
            <flux:callout icon="information-circle" heading="券種を1つ以上登録すると公開できます" />
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>券種名</flux:table.column>
                    <flux:table.column align="end">価格</flux:table.column>
                    <flux:table.column align="end">定員</flux:table.column>
                    <flux:table.column>販売期間</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->ticketTypes as $ticketType)
                        <flux:table.row :key="'ticket-type-'.$ticketType->id">
                            <flux:table.cell variant="strong">{{ $ticketType->name }}</flux:table.cell>
                            <flux:table.cell align="end">{{ number_format($ticketType->price) }}円</flux:table.cell>
                            <flux:table.cell align="end">{{ number_format($ticketType->capacity) }}</flux:table.cell>
                            <flux:table.cell>
                                {{ $ticketType->sales_starts_at->isoFormat('M月D日(ddd) HH:mm') }}
                                〜 {{ $ticketType->sales_ends_at->isoFormat('M月D日(ddd) HH:mm') }}
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <div class="flex justify-end gap-2">
                                    <flux:button size="sm" icon="pencil-square" wire:click="editTicketType({{ $ticketType->id }})">
                                        編集
                                    </flux:button>
                                    <flux:button
                                        size="sm"
                                        variant="danger"
                                        icon="trash"
                                        wire:click="deleteTicketType({{ $ticketType->id }})"
                                        wire:confirm="この券種を削除しますか？"
                                    >
                                        削除
                                    </flux:button>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif

        <form wire:submit="saveTicketType" class="space-y-6 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading>{{ $editingTicketTypeId === null ? '券種の追加' : '券種の編集' }}</flux:heading>

            <flux:input wire:model="ticketTypeName" label="券種名" type="text" required maxlength="50" placeholder="一般、学生など" />

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input wire:model="ticketTypePrice" label="価格（円）" type="number" required min="0" step="1" />

                <flux:input wire:model="ticketTypeCapacity" label="定員" type="number" required min="1" step="1" />
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input wire:model="ticketTypeSalesStartsAt" label="販売開始日時" type="datetime-local" required />

                <flux:input wire:model="ticketTypeSalesEndsAt" label="販売終了日時" type="datetime-local" required />
            </div>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">
                    {{ $editingTicketTypeId === null ? '追加' : '更新' }}
                </flux:button>

                @if ($editingTicketTypeId !== null)
                    <flux:button variant="ghost" wire:click="cancelEditTicketType">キャンセル</flux:button>
                @endif
            </div>
        </form>
    </div>

    <flux:separator />

    <div class="space-y-3">
        <flux:heading>イベントの削除</flux:heading>
        <flux:text>削除すると元に戻せません。</flux:text>
        <flux:button variant="danger" wire:click="deleteEvent" wire:confirm="このイベントを削除しますか？">
            削除する
        </flux:button>
    </div>
</section>
