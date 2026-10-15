<?php

use App\Models\Event;
use App\Services\OrderService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('イベント詳細')] class extends Component {
    public Event $event;

    /**
     * Selected quantities keyed by ticket type ID.
     *
     * @var array<int, int|string>
     */
    public array $quantities = [];

    /**
     * Mount the component.
     *
     * Events the viewer may not see are reported as 404 rather than 403.
     */
    public function mount(Event $event): void
    {
        // abort_unless(Gate::allows('view', $event), 404);
        abort_unless(auth()->user()?->can('view', $event) ?? $event->isPublished(), 404);

        $this->event = $event->load(['organizer', 'ticketTypes' => fn ($query) => $query->orderBy('price')]);
    }

    /**
     * Keep the selected quantities in the session and move on to the purchase page.
     *
     * The quantities are checked again when the order is created.
     */
    public function proceed(): void
    {
        abort_unless($this->event->isPurchasable(), 404);

        $quantities = collect($this->quantities)
            ->only($this->event->ticketTypes->modelKeys())
            ->map(fn (int|string $quantity): int => (int) $quantity)
            ->filter(fn (int $quantity): bool => $quantity > 0)
            ->all();

        if ($quantities === []) {
            $this->addError('quantities', 'チケットの枚数を選んでください。');

            return;
        }

        session()->put("purchase.{$this->event->id}", $quantities);

        $this->redirectRoute('events.purchase', $this->event);
    }
}; ?>

<section class="w-full max-w-3xl space-y-8">
    <div class="space-y-3">
        @unless ($event->isPublished())
            <flux:badge :color="$event->status->badgeColor()" size="sm">{{ $event->status->label() }}</flux:badge>
        @endunless

        <flux:heading size="xl" level="1">{{ $event->title }}</flux:heading>

        <dl class="grid gap-2 text-sm text-zinc-600 dark:text-zinc-300">
            <div class="flex items-start gap-2">
                <dt><flux:icon.calendar-days variant="mini" /><span class="sr-only">日時</span></dt>
                <dd>{{ $event->starts_at->isoFormat('M月D日(ddd) HH:mm') }} 〜 {{ $event->ends_at->isoFormat('M月D日(ddd) HH:mm') }}</dd>
            </div>
            <div class="flex items-start gap-2">
                <dt><flux:icon.map-pin variant="mini" /><span class="sr-only">会場</span></dt>
                <dd>{{ $event->venue }}</dd>
            </div>
            <div class="flex items-start gap-2">
                <dt><flux:icon.user variant="mini" /><span class="sr-only">主催者</span></dt>
                <dd>主催: {{ $event->organizer->name }}</dd>
            </div>
        </dl>
    </div>

    <flux:text class="whitespace-pre-line">{{ $event->description }}</flux:text>

    <div class="space-y-4">
        <flux:heading size="lg" level="2">券種</flux:heading>

        @if ($event->ticketTypes->isEmpty())
            <flux:text>券種はまだありません。</flux:text>
        @else
            @php($hasPurchasableTicketType = false)

            <form wire:submit="proceed" class="space-y-4">
                <div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                    @foreach ($event->ticketTypes as $ticketType)
                        @php($remaining = $ticketType->remaining())
                        @php($maxQuantity = $event->isPurchasable() && $ticketType->isOnSale() ? min(OrderService::MAX_QUANTITY, $remaining) : 0)
                        @php($hasPurchasableTicketType = $hasPurchasableTicketType || $maxQuantity > 0)

                        <div class="flex flex-wrap items-center gap-4 p-4" wire:key="ticket-type-{{ $ticketType->id }}">
                            <div class="min-w-0 flex-1 space-y-1">
                                <flux:heading>{{ $ticketType->name }}</flux:heading>
                                <flux:text size="sm">
                                    販売期間: {{ $ticketType->sales_starts_at->isoFormat('M月D日(ddd) HH:mm') }} 〜 {{ $ticketType->sales_ends_at->isoFormat('M月D日(ddd) HH:mm') }}
                                </flux:text>
                            </div>

                            <div class="flex items-center gap-3">
                                @if ($remaining === 0)
                                    <flux:badge color="red" size="sm">売り切れ</flux:badge>
                                @else
                                    <flux:text size="sm">残り {{ number_format($remaining) }}枚</flux:text>
                                @endif

                                @unless ($ticketType->isOnSale())
                                    <flux:badge color="zinc" size="sm">販売期間外</flux:badge>
                                @endunless

                                <flux:heading>
                                    {{ $ticketType->price === 0 ? '無料' : number_format($ticketType->price).'円' }}
                                </flux:heading>

                                @if ($maxQuantity > 0)
                                    <div class="w-24">
                                        <flux:select wire:model="quantities.{{ $ticketType->id }}" size="sm" aria-label="{{ $ticketType->name }}の枚数">
                                            @foreach (range(0, $maxQuantity) as $quantity)
                                                <flux:select.option :value="$quantity">{{ $quantity }}枚</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($hasPurchasableTicketType)
                    <flux:error name="quantities" />

                    <div class="flex justify-end">
                        <flux:button variant="primary" type="submit">購入手続きへ進む</flux:button>
                    </div>
                @endif
            </form>
        @endif
    </div>
</section>
