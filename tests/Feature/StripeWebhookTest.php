<?php

use App\Enums\OrderStatus;
use App\Mail\OrderPaidMail;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    config(['services.stripe.webhook_secret' => 'whsec_test']);
});

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

test('a webhook that marks the order as paid sends one purchase mail to the buyer', function () {
    Mail::fake();
    $order = pendingOrderWithThreeSeats();

    sendCheckoutCompletedWebhook($order)->assertOk();
    sendCheckoutCompletedWebhook($order)->assertOk();

    Mail::assertSentCount(1);
    Mail::assertSent(OrderPaidMail::class, fn (OrderPaidMail $mail): bool => $mail->hasTo($order->user->email)
        && $mail->order->is($order));
});

test('a webhook that does not mark the order as paid sends no mail', function () {
    Mail::fake();
    $order = pendingOrderWithThreeSeats();

    sendCheckoutCompletedWebhook($order, paymentStatus: 'unpaid')->assertOk();

    Mail::assertNothingSent();
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
