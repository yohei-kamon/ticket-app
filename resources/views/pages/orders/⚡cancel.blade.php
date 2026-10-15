<?php

use App\Models\Order;
use App\Services\OrderService;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('支払いの中断')] class extends Component {
    public Order $order;

    /**
     * Mount the component.
     *
     * Only the buyer may cancel the order. Pending orders are canceled and their tickets released.
     */
    public function mount(Order $order, OrderService $orderService): void
    {
        $this->authorize('cancel', $order);

        $orderService->cancel($order);

        $this->order = $order->load('event');
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    @if ($order->isPaid())
        <flux:callout variant="success" icon="check-circle" heading="この注文は支払い済みです" />
    @else
        <flux:callout variant="warning" icon="exclamation-triangle" heading="支払いを中断しました。注文を取り消しました" />
    @endif

    <dl class="grid gap-2 text-sm">
        <div class="flex gap-2">
            <dt class="text-zinc-500 dark:text-zinc-400">イベント</dt>
            <dd>{{ $order->event->title }}</dd>
        </div>
        <div class="flex gap-2">
            <dt class="text-zinc-500 dark:text-zinc-400">合計</dt>
            <dd>{{ number_format($order->total_amount) }}円</dd>
        </div>
    </dl>

    <flux:button :href="route('events.show', $order->event)" wire:navigate>イベントに戻る</flux:button>
</section>
