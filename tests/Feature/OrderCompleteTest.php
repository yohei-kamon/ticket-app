<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\CheckoutService;
use Livewire\Livewire;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiConnectionException;

/**
 * Create a pending order with a Checkout Session and two seats.
 */
function pendingOrderWithSession(): Order
{
    $order = Order::factory()->create(['stripe_checkout_session_id' => 'cs_test']);
    OrderItem::factory()->for($order)->create(['quantity' => 2]);

    return $order;
}

test('the completion page confirms the payment with Stripe and issues the tickets', function () {
    $order = pendingOrderWithSession();

    $this->mock(CheckoutService::class)
        ->shouldReceive('retrieveSession')
        ->once()
        ->with('cs_test')
        ->andReturn(Session::constructFrom(['id' => 'cs_test', 'payment_status' => 'paid', 'payment_intent' => 'pi_test']));

    $this->actingAs($order->user)
        ->get(route('orders.complete', ['order' => $order, 'session_id' => 'cs_test']))
        ->assertOk()
        ->assertSee('購入が完了しました')
        ->assertSee('2枚');

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->tickets()->count())->toBe(2);
});

test('the completion page keeps checking while Stripe has not received the payment', function () {
    $order = pendingOrderWithSession();

    $this->mock(CheckoutService::class)
        ->shouldReceive('retrieveSession')
        ->andReturn(Session::constructFrom(['id' => 'cs_test', 'payment_status' => 'unpaid']));

    $this->actingAs($order->user)
        ->get(route('orders.complete', ['order' => $order, 'session_id' => 'cs_test']))
        ->assertOk()
        ->assertSee('支払いを確認中です')
        ->assertSeeHtml('wire:poll.5s="refreshStatus"');

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($order->tickets()->count())->toBe(0);
});

test('a session ID that does not belong to the order is not looked up', function () {
    $order = pendingOrderWithSession();

    $this->mock(CheckoutService::class)->shouldNotReceive('retrieveSession');

    $this->actingAs($order->user)
        ->get(route('orders.complete', ['order' => $order, 'session_id' => 'cs_other']))
        ->assertOk()
        ->assertSee('支払いを確認中です');

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

test('a paid order is shown without asking Stripe again', function () {
    $order = Order::factory()->paid()->create(['stripe_checkout_session_id' => 'cs_test']);
    OrderItem::factory()->for($order)->create(['quantity' => 2]);

    $this->mock(CheckoutService::class)->shouldNotReceive('retrieveSession');

    $this->actingAs($order->user)
        ->get(route('orders.complete', ['order' => $order, 'session_id' => 'cs_test']))
        ->assertOk()
        ->assertSee('購入が完了しました');
});

test('the order stays in the checking state when Stripe cannot be reached', function () {
    $order = pendingOrderWithSession();

    $this->mock(CheckoutService::class)
        ->shouldReceive('retrieveSession')
        ->andThrow(new ApiConnectionException('network error'));

    $this->actingAs($order->user)
        ->get(route('orders.complete', ['order' => $order, 'session_id' => 'cs_test']))
        ->assertOk()
        ->assertSee('支払いを確認中です');

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($order->tickets()->count())->toBe(0);
});

test('refreshing switches to the completed view once Stripe reports the payment', function () {
    $order = pendingOrderWithSession();

    $this->mock(CheckoutService::class)
        ->shouldReceive('retrieveSession')
        ->with('cs_test')
        ->andReturn(
            Session::constructFrom(['id' => 'cs_test', 'payment_status' => 'unpaid']),
            Session::constructFrom(['id' => 'cs_test', 'payment_status' => 'paid', 'payment_intent' => 'pi_test']),
        );

    $this->actingAs($order->user);

    Livewire::withQueryParams(['session_id' => 'cs_test'])
        ->test('pages::orders.complete', ['order' => $order])
        ->assertSee('支払いを確認中です')
        ->call('refreshStatus')
        ->assertSee('購入が完了しました')
        ->assertSee('2枚')
        ->assertDontSee('支払いを確認中です');

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});
