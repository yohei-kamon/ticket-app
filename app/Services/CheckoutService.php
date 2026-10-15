<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class CheckoutService
{
    /**
     * Create a new class instance.
     */
    public function __construct(private StripeClient $stripe) {}

    /**
     * Create a Stripe Checkout Session for the order and return the payment page URL.
     *
     * The order's items are sent as line items with their unit prices at the time of the order.
     *
     * @throws ApiErrorException
     */
    public function createSession(Order $order): string
    {
        $order->loadMissing(['event', 'items.ticketType']);

        $session = $this->stripe->checkout->sessions->create([
            'mode' => 'payment',
            'line_items' => $order->items->map(fn (OrderItem $item): array => [
                'price_data' => [
                    'currency' => 'jpy',
                    'unit_amount' => $item->unit_price,
                    'product_data' => [
                        'name' => "{$order->event->title} {$item->ticketType->name}",
                    ],
                ],
                'quantity' => $item->quantity,
            ])->all(),
            'metadata' => [
                'order_id' => $order->id,
            ],
            'success_url' => route('orders.complete', $order).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('orders.cancel', $order),
        ]);

        $order->forceFill(['stripe_checkout_session_id' => $session->id])->save();

        return (string) $session->url;
    }

    /**
     * Retrieve a Stripe Checkout Session.
     *
     * @throws ApiErrorException
     */
    public function retrieveSession(string $id): Session
    {
        return $this->stripe->checkout->sessions->retrieve($id);
    }
}
