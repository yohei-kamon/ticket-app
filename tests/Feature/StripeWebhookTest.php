<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config(['services.stripe.webhook_secret' => 'whsec_test']);
});

/**
 * Send a checkout.session.completed webhook for the order, signed with the given secret.
 */
function sendCheckoutCompletedWebhook(
    Order $order,
    string $secret = 'whsec_test',
    string $type = 'checkout.session.completed',
    string $paymentStatus = 'paid',
    ?string $orderId = null,
): TestResponse {
    $payload = json_encode([
        'id' => 'evt_test',
        'object' => 'event',
        'type' => $type,
        'data' => [
            'object' => [
                'id' => 'cs_test',
                'object' => 'checkout.session',
                'payment_status' => $paymentStatus,
                'payment_intent' => 'pi_test',
                'metadata' => ['order_id' => $orderId ?? (string) $order->id],
            ],
        ],
    ]);
    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

    return test()->call('POST', route('stripe.webhook'), server: [
        'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        'CONTENT_TYPE' => 'application/json',
    ], content: $payload);
}

/**
 * Create a pending order with three seats in total.
 */
function pendingOrderWithThreeSeats(): Order
{
    $order = Order::factory()->create();
    OrderItem::factory()->for($order)->create(['quantity' => 2]);
    OrderItem::factory()->for($order)->create(['quantity' => 1]);

    return $order;
}

test('a signed checkout.session.completed webhook marks the order as paid and issues its tickets', function () {
    $order = pendingOrderWithThreeSeats();

    sendCheckoutCompletedWebhook($order)->assertOk();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->paid_at)->not->toBeNull()
        ->and($order->tickets()->count())->toBe(3);
});

test('webhooks with an invalid signature are rejected', function () {
    $order = pendingOrderWithThreeSeats();

    sendCheckoutCompletedWebhook($order, secret: 'whsec_wrong')->assertBadRequest();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($order->tickets()->count())->toBe(0);
});

test('the same webhook sent twice does not issue tickets twice', function () {
    $order = pendingOrderWithThreeSeats();

    sendCheckoutCompletedWebhook($order)->assertOk();
    sendCheckoutCompletedWebhook($order)->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->tickets()->count())->toBe(3);
});

test('webhooks without a signature header are rejected', function () {
    $order = pendingOrderWithThreeSeats();

    $this->call('POST', route('stripe.webhook'), server: [
        'CONTENT_TYPE' => 'application/json',
    ], content: json_encode(['type' => 'checkout.session.completed']))->assertBadRequest();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($order->tickets()->count())->toBe(0);
});

test('an unpaid checkout session leaves the order pending', function () {
    $order = pendingOrderWithThreeSeats();

    sendCheckoutCompletedWebhook($order, paymentStatus: 'unpaid')->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($order->tickets()->count())->toBe(0);
});

test('events other than checkout.session.completed are acknowledged without changing the order', function () {
    $order = pendingOrderWithThreeSeats();

    sendCheckoutCompletedWebhook($order, type: 'payment_intent.succeeded')->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($order->tickets()->count())->toBe(0);
});

test('a webhook for an order that does not exist is acknowledged', function () {
    $order = pendingOrderWithThreeSeats();

    sendCheckoutCompletedWebhook($order, orderId: '999999')->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($order->tickets()->count())->toBe(0);
});

test('a canceled order is not marked as paid by a webhook', function () {
    $order = Order::factory()->canceled()->create();
    OrderItem::factory()->for($order)->create(['quantity' => 2]);

    sendCheckoutCompletedWebhook($order)->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Canceled)
        ->and($order->tickets()->count())->toBe(0);
});
