<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\CheckoutService;
use App\Services\OrderService;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Stripe\Exception\ApiErrorException;

new #[Title('注文完了')] class extends Component {
    public Order $order;

    /**
     * The Checkout Session ID Stripe appended to the success URL.
     */
    #[Locked]
    public ?string $sessionId = null;

    /**
     * Mount the component.
     *
     * Only the buyer, the event's organizer and admins may open the order.
     * The payment is confirmed with Stripe in case the webhook has not arrived yet.
     */
    public function mount(Order $order, CheckoutService $checkoutService, OrderService $orderService): void
    {
        $this->authorize('view', $order);

        $this->order = $order->load('event');
        $this->sessionId = is_string(request()->query('session_id')) ? request()->query('session_id') : null;

        $this->confirmPayment($checkoutService, $orderService);
    }

    /**
     * Check the payment again while the order is still pending.
     */
    public function refreshStatus(CheckoutService $checkoutService, OrderService $orderService): void
    {
        $this->authorize('view', $this->order);

        $this->order->refresh();

        $this->confirmPayment($checkoutService, $orderService);
    }

    /**
     * Mark the order as paid when its Checkout Session has been paid.
     *
     * Only the session that belongs to the order is looked up.
     */
    private function confirmPayment(CheckoutService $checkoutService, OrderService $orderService): void
    {
        if ($this->order->status !== OrderStatus::Pending
            || $this->sessionId === null
            || $this->order->stripe_checkout_session_id === null
            || ! hash_equals($this->order->stripe_checkout_session_id, $this->sessionId)) {
            return;
        }

        try {
            $session = $checkoutService->retrieveSession($this->sessionId);
        } catch (ApiErrorException $exception) {
            report($exception);

            return;
        }

        if ($session->payment_status === 'paid') {
            $orderService->markAsPaid($this->order, is_string($session->payment_intent) ? $session->payment_intent : null);
        }
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    @if ($order->isPaid())
        <flux:callout variant="success" icon="check-circle" heading="購入が完了しました" />
    @elseif ($order->status === \App\Enums\OrderStatus::Pending)
        <div wire:poll.5s="refreshStatus">
            <flux:callout icon="clock" heading="支払いを確認中です">
                <flux:callout.text>確認が終わるとこの画面が自動で切り替わります。しばらくお待ちください。</flux:callout.text>
            </flux:callout>
        </div>
    @else
        <flux:callout variant="warning" icon="exclamation-triangle" heading="この注文は取り消されています" />
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
        @if ($order->isPaid())
            <div class="flex gap-2">
                <dt class="text-zinc-500 dark:text-zinc-400">チケット</dt>
                <dd>{{ $order->tickets()->count() }}枚</dd>
            </div>
        @endif
    </dl>

    <flux:button :href="route('events.show', $order->event)" wire:navigate>イベントに戻る</flux:button>
</section>
