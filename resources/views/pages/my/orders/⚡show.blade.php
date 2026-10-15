<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('注文の詳細')] class extends Component {
    public Order $order;

    /**
     * Mount the component.
     *
     * Only the buyer, the event's organizer and admins may open the order.
     */
    public function mount(Order $order): void
    {
        $this->authorize('view', $order);

        $this->order = $order->load('event', 'items.ticketType', 'items.tickets');
    }
}; ?>

<section class="w-full max-w-3xl space-y-6">
    <div class="flex flex-wrap items-center gap-4">
        <flux:heading size="xl" level="1">注文の詳細</flux:heading>

        <flux:badge :color="$order->status->badgeColor()" size="sm">
            {{ $order->status->label() }}
        </flux:badge>
    </div>

    @if ($order->status === OrderStatus::Pending)
        <flux:callout variant="warning" icon="clock" heading="支払いが完了していません">
            <flux:callout.text>支払いが確認できるとチケットが発行されます。支払いを済ませた場合は、しばらく待ってからこの画面を開き直してください。</flux:callout.text>
        </flux:callout>
    @elseif ($order->status === OrderStatus::Canceled)
        <flux:callout variant="warning" icon="exclamation-triangle" heading="この注文は取り消されています" />
    @endif

    <dl class="grid gap-2 text-sm">
        <div class="flex gap-2">
            <dt class="text-zinc-500 dark:text-zinc-400">イベント</dt>
            <dd>
                <flux:link :href="route('events.show', $order->event)" wire:navigate>{{ $order->event->title }}</flux:link>
            </dd>
        </div>
        <div class="flex gap-2">
            <dt class="text-zinc-500 dark:text-zinc-400">日時</dt>
            <dd>{{ $order->event->starts_at->isoFormat('M月D日(ddd) HH:mm') }}</dd>
        </div>
        <div class="flex gap-2">
            <dt class="text-zinc-500 dark:text-zinc-400">会場</dt>
            <dd>{{ $order->event->venue }}</dd>
        </div>
        <div class="flex gap-2">
            <dt class="text-zinc-500 dark:text-zinc-400">注文日</dt>
            <dd>{{ $order->created_at->isoFormat('M月D日(ddd) HH:mm') }}</dd>
        </div>
    </dl>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>券種</flux:table.column>
            <flux:table.column align="end">単価</flux:table.column>
            <flux:table.column align="end">枚数</flux:table.column>
            <flux:table.column align="end">小計</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($order->items as $item)
                <flux:table.row :key="'item-'.$item->id">
                    <flux:table.cell>{{ $item->ticketType->name }}</flux:table.cell>
                    <flux:table.cell align="end">{{ number_format($item->unit_price) }}円</flux:table.cell>
                    <flux:table.cell align="end">{{ $item->quantity }}枚</flux:table.cell>
                    <flux:table.cell align="end">{{ number_format($item->unit_price * $item->quantity) }}円</flux:table.cell>
                </flux:table.row>
            @endforeach

            <flux:table.row>
                <flux:table.cell class="font-medium">合計</flux:table.cell>
                <flux:table.cell />
                <flux:table.cell />
                <flux:table.cell align="end" class="font-medium">{{ number_format($order->total_amount) }}円</flux:table.cell>
            </flux:table.row>
        </flux:table.rows>
    </flux:table>

    @if ($order->isPaid())
        <div class="space-y-3">
            <flux:heading size="lg" level="2">チケット</flux:heading>

            <div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                @foreach ($order->items as $item)
                    @foreach ($item->tickets as $ticket)
                        <div class="flex flex-wrap items-center justify-between gap-2 p-4" wire:key="ticket-{{ $ticket->id }}">
                            <flux:text>{{ $item->ticketType->name }}</flux:text>
                            <code class="font-mono text-sm">{{ $ticket->code }}</code>
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>
    @endif

    <flux:button icon="arrow-left" :href="route('my.orders.index')" wire:navigate>注文一覧に戻る</flux:button>
</section>
