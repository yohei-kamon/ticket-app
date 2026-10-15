<?php

use App\Enums\OrderStatus;
use App\Mail\OrderPaidMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Services\CheckoutService;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Stripe\Checkout\Session;

beforeEach(function () {
    config(['services.stripe.webhook_secret' => 'whsec_test']);
});

/**
 * Create a pending order with a Checkout Session and two seats.
 */
function flowPendingOrder(): Order
{
    $order = Order::factory()->create(['stripe_checkout_session_id' => 'cs_test']);
    OrderItem::factory()->for($order)->create(['quantity' => 2]);

    return $order;
}

/**
 * Make the completion page see the session as paid.
 */
function flowMockPaidSession(): void
{
    test()->mock(CheckoutService::class)
        ->shouldReceive('retrieveSession')
        ->with('cs_test')
        ->andReturn(Session::constructFrom(['id' => 'cs_test', 'payment_status' => 'paid', 'payment_intent' => 'pi_test']));
}

test('the completion page sends one purchase mail to the buyer when it marks the order as paid', function () {
    Mail::fake();
    $order = flowPendingOrder();
    flowMockPaidSession();

    $this->actingAs($order->user)
        ->get(route('orders.complete', ['order' => $order, 'session_id' => 'cs_test']))
        ->assertOk();

    Mail::assertSentCount(1);
    Mail::assertSent(OrderPaidMail::class, fn (OrderPaidMail $mail): bool => $mail->hasTo($order->user->email)
        && $mail->order->is($order));
});

test('the completion page followed by the webhook still sends only one mail', function () {
    Mail::fake();
    $order = flowPendingOrder();
    flowMockPaidSession();

    $this->actingAs($order->user)
        ->get(route('orders.complete', ['order' => $order, 'session_id' => 'cs_test']))
        ->assertOk();
    sendCheckoutCompletedWebhook($order)->assertOk();

    Mail::assertSentCount(1);
    expect($order->tickets()->count())->toBe(2);
});

test('the webhook followed by the completion page still sends only one mail', function () {
    Mail::fake();
    $order = flowPendingOrder();
    $this->mock(CheckoutService::class)->shouldNotReceive('retrieveSession');

    sendCheckoutCompletedWebhook($order)->assertOk();
    $this->actingAs($order->user)
        ->get(route('orders.complete', ['order' => $order, 'session_id' => 'cs_test']))
        ->assertOk();

    Mail::assertSentCount(1);
});

test('a mail failure keeps the order paid and the webhook returns 200', function () {
    Exceptions::fake();
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('smtp down'));
    $order = flowPendingOrder();

    sendCheckoutCompletedWebhook($order)->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->tickets()->count())->toBe(2);
    Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'smtp down');
});

test('a mail failure on the completion page keeps the order paid', function () {
    Exceptions::fake();
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('smtp down'));
    $order = flowPendingOrder();
    flowMockPaidSession();

    $this->actingAs($order->user)
        ->get(route('orders.complete', ['order' => $order, 'session_id' => 'cs_test']))
        ->assertOk()
        ->assertSee('購入が完了しました');

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('the purchase mail uses the unit price saved on the order, not the current ticket price', function () {
    $ticketType = TicketType::factory()->create(['price' => 9900]);
    $order = Order::factory()->paid()->create(['total_amount' => 2400]);
    OrderItem::factory()->for($order)->for($ticketType)->create(['quantity' => 3, 'unit_price' => 800]);

    $mail = new OrderPaidMail($order);

    $mail->assertSeeInHtml('2,400円')
        ->assertDontSeeInHtml('29,700円');
});
