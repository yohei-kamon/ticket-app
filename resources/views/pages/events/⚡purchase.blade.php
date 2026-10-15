<?php

use App\Exceptions\PurchaseException;
use App\Models\Event;
use App\Models\TicketType;
use App\Services\CheckoutService;
use App\Services\OrderService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Stripe\Exception\ApiErrorException;

new #[Title('購入手続き')] class extends Component {
    public Event $event;

    /**
     * Selected quantities keyed by ticket type ID.
     *
     * @var array<int, int>
     */
    #[Locked]
    public array $quantities = [];

    /**
     * Mount the component.
     */
    public function mount(Event $event): void
    {
        abort_unless($event->isPurchasable(), 404);

        $this->event = $event;
        $this->quantities = session("purchase.{$event->id}", []);

        if ($this->quantities === []) {
            $this->redirectRoute('events.show', $event, navigate: true);
        }
    }

    /**
     * Get the selected ticket types with their quantities and subtotals.
     *
     * @return Collection<int, array{ticketType: TicketType, quantity: int, subtotal: int}>
     */
    #[Computed]
    public function lines(): Collection
    {
        return $this->event->ticketTypes()
            ->whereKey(array_keys($this->quantities))
            ->orderBy('price')
            ->get()
            ->map(fn (TicketType $ticketType): array => [
                'ticketType' => $ticketType,
                'quantity' => $this->quantities[$ticketType->id],
                'subtotal' => $ticketType->price * $this->quantities[$ticketType->id],
            ]);
    }

    /**
     * Create the order and send the user to the Stripe payment page.
     *
     * Free orders skip Stripe and go straight to the completion page.
     */
    public function checkout(OrderService $orderService, CheckoutService $checkoutService): void
    {
        try {
            $order = $orderService->createOrder(Auth::user(), $this->event, $this->quantities);
        } catch (PurchaseException $exception) {
            $this->addError('purchase', $exception->getMessage());

            return;
        }

        session()->forget("purchase.{$this->event->id}");

        if ($order->total_amount === 0) {
            $this->redirectRoute('orders.complete', $order, navigate: true);

            return;
        }

        try {
            $url = $checkoutService->createSession($order);
        } catch (ApiErrorException $exception) {
            report($exception);
            $orderService->cancel($order);
            $this->addError('purchase', '決済ページを開けませんでした。時間をおいてもう一度お試しください。');

            return;
        }

        $this->redirect($url);
    }
}; ?>

<section class="w-full max-w-3xl space-y-6">
    <div class="space-y-1">
        <flux:heading size="xl" level="1">購入手続き</flux:heading>
        <flux:text>{{ $event->title }}（{{ $event->starts_at->isoFormat('M月D日(ddd) HH:mm') }}）</flux:text>
    </div>

    @error('purchase')
        <flux:callout variant="danger" icon="x-circle" heading="{{ $message }}" />
    @enderror

    <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-sm">
            <thead class="bg-zinc-50 text-left text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                <tr>
                    <th class="px-4 py-3 font-medium">券種</th>
                    <th class="px-4 py-3 text-right font-medium">単価</th>
                    <th class="px-4 py-3 text-right font-medium">枚数</th>
                    <th class="px-4 py-3 text-right font-medium">小計</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($this->lines as $line)
                    <tr wire:key="line-{{ $line['ticketType']->id }}">
                        <td class="px-4 py-3">{{ $line['ticketType']->name }}</td>
                        <td class="px-4 py-3 text-right">{{ $line['ticketType']->price === 0 ? '無料' : number_format($line['ticketType']->price).'円' }}</td>
                        <td class="px-4 py-3 text-right">{{ $line['quantity'] }}枚</td>
                        <td class="px-4 py-3 text-right">{{ number_format($line['subtotal']) }}円</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="border-t border-zinc-200 dark:border-zinc-700">
                <tr>
                    <th colspan="3" class="px-4 py-3 text-right font-medium">合計</th>
                    <td class="px-4 py-3 text-right text-base font-semibold">{{ number_format($this->lines->sum('subtotal')) }}円</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="flex items-center justify-end gap-4">
        <flux:button variant="ghost" :href="route('events.show', $event)" wire:navigate>枚数を選び直す</flux:button>
        <flux:button variant="primary" wire:click="checkout">支払いへ進む</flux:button>
    </div>
</section>
