<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\PurchaseException;
use App\Mail\OrderPaidMail;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class OrderService
{
    /**
     * Minutes a pending order keeps its tickets reserved.
     *
     * Longer than the Stripe payment page stays open, with a margin for creating the session
     * and for the webhook or the completion page to confirm a payment made at the last moment.
     */
    public const PENDING_MINUTES = CheckoutService::EXPIRES_MINUTES + 5;

    /**
     * Maximum number of tickets per ticket type in one order.
     */
    public const MAX_QUANTITY = 10;

    /**
     * Create a pending order and its items.
     *
     * Prices are taken from the ticket types at the time of the order.
     *
     * @param  array<int, int>  $quantities  Quantities keyed by ticket type ID.
     *
     * @throws PurchaseException
     */
    public function createOrder(User $user, Event $event, array $quantities): Order
    {
        return DB::transaction(function () use ($user, $event, $quantities): Order {
            if (! $event->isPurchasable()) {
                throw new PurchaseException('このイベントのチケットは購入できません。');
            }

            if ($quantities === []) {
                throw new PurchaseException('チケットの枚数を選んでください。');
            }

            $ticketTypes = $event->ticketTypes()
                ->whereKey(array_keys($quantities))
                ->lockForUpdate()
                ->get();

            if ($ticketTypes->count() !== count($quantities)) {
                throw new PurchaseException('選択した券種が見つかりません。');
            }

            $ticketTypes->each(fn (TicketType $ticketType) => $this->ensurePurchasable($ticketType, $quantities[$ticketType->id]));

            $order = $user->orders()->create([
                'event_id' => $event->id,
                'total_amount' => $ticketTypes->sum(fn (TicketType $ticketType): int => $ticketType->price * $quantities[$ticketType->id]),
            ]);

            $order->items()->createMany($ticketTypes->map(fn (TicketType $ticketType): array => [
                'ticket_type_id' => $ticketType->id,
                'quantity' => $quantities[$ticketType->id],
                'unit_price' => $ticketType->price,
            ])->all());

            return $order;
        });
    }

    /**
     * Cancel the order if it is still pending, releasing its reserved tickets.
     *
     * Orders that have already been paid are left untouched.
     */
    public function cancel(Order $order): bool
    {
        $canceled = Order::query()
            ->whereKey($order->id)
            ->where('status', OrderStatus::Pending)
            ->update(['status' => OrderStatus::Canceled]) === 1;

        $order->refresh();

        return $canceled;
    }

    /**
     * Mark the order as paid if it is still pending and issue one ticket per purchased seat.
     *
     * The status is changed with a conditional update so that the webhook and the completion
     * page can both call this safely. Returns false when the order was already processed.
     *
     * The payment intent ID is not stored yet; it will be kept for refunds.
     */
    public function markAsPaid(Order $order, ?string $paymentIntentId = null): bool
    {
        $paid = DB::transaction(function () use ($order): bool {
            $updated = Order::query()
                ->whereKey($order->id)
                ->where('status', OrderStatus::Pending)
                ->update(['status' => OrderStatus::Paid, 'paid_at' => now()]) === 1;

            if (! $updated) {
                return false;
            }

            $order->items()->get()->each(fn (OrderItem $item) => $item->tickets()->createMany(
                collect(range(1, $item->quantity))->map(fn (): array => ['code' => (string) Str::uuid()])->all(),
            ));

            return true;
        });

        $order->refresh();

        if ($paid) {
            $this->sendPaidMail($order);
        }

        return $paid;
    }

    /**
     * Send the purchase confirmation to the buyer.
     *
     * A failure is only reported so that the payment, which is already recorded, is not rolled back
     * and the webhook is still acknowledged.
     */
    private function sendPaidMail(Order $order): void
    {
        try {
            Mail::to($order->user)->send(new OrderPaidMail($order));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Ensure the given quantity of the ticket type can be purchased.
     *
     * @throws PurchaseException
     */
    private function ensurePurchasable(TicketType $ticketType, mixed $quantity): void
    {
        if (! is_int($quantity) || $quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw new PurchaseException("「{$ticketType->name}」は1〜".self::MAX_QUANTITY.'枚の範囲で選んでください。');
        }

        if (! $ticketType->isOnSale()) {
            throw new PurchaseException("「{$ticketType->name}」は販売期間外です。");
        }

        $remaining = $ticketType->remaining();

        if ($remaining < $quantity) {
            throw new PurchaseException("「{$ticketType->name}」の残りは{$remaining}枚です。");
        }
    }
}
