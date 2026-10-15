<?php

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Models\User;

test('the buyer cancels a pending order and its tickets are released', function () {
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create(['capacity' => 5]);
    $order = Order::factory()->for($event)->create();
    OrderItem::factory()->for($order)->for($ticketType)->create(['quantity' => 2]);

    expect($ticketType->remaining())->toBe(3);

    $this->actingAs($order->user)
        ->get(route('orders.cancel', $order))
        ->assertOk()
        ->assertSee('支払いを中断しました。注文を取り消しました');

    expect($order->fresh()->status)->toBe(OrderStatus::Canceled)
        ->and($ticketType->remaining())->toBe(5);
});

test('paid orders are not canceled', function () {
    $order = Order::factory()->paid()->create();

    $this->actingAs($order->user)
        ->get(route('orders.cancel', $order))
        ->assertOk()
        ->assertSee('この注文は支払い済みです');

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('only the buyer can cancel the order', function () {
    $organizer = User::factory()->organizer()->create();
    $order = Order::factory()->for(Event::factory()->published()->for($organizer, 'organizer'))->create();

    $this->get(route('orders.cancel', $order))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('orders.cancel', $order))
        ->assertForbidden();

    $this->actingAs($organizer)
        ->get(route('orders.cancel', $order))
        ->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});
