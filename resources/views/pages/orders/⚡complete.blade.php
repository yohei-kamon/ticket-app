<?php

use App\Models\Order;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('注文完了')] class extends Component {
    public Order $order;

    /**
     * Mount the component.
     *
     * Only the buyer, the event's organizer and admins may open the order.
     */
    public function mount(Order $order): void
    {
        $this->authorize('view', $order);

        $this->order = $order->load('event');
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <flux:callout variant="success" icon="check-circle" heading="注文を受け付けました（支払いは平日課題で実装）" />

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
