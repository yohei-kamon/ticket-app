<?php

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Database\QueryException;

test('new orders are pending', function () {
    $user = User::factory()->create();
    $event = Event::factory()->published()->create();

    $order = $user->orders()->create(['event_id' => $event->id, 'total_amount' => 3000]);

    expect($order->refresh()->status)->toBe(OrderStatus::Pending)
        ->and($order->isPaid())->toBeFalse();
});

test('order items belong to an order and a ticket type', function () {
    $item = OrderItem::factory()->create();

    expect($item->order->items->first()->is($item))->toBeTrue()
        ->and($item->ticketType->orderItems->first()->is($item))->toBeTrue();
});

test('ticket types with orders cannot be deleted', function () {
    $item = OrderItem::factory()->create();

    expect(fn () => $item->ticketType->delete())->toThrow(QueryException::class);
});

test('events with orders cannot be deleted', function () {
    $order = Order::factory()->create();

    expect(fn () => $order->event->delete())->toThrow(QueryException::class);
});

test('deleting an order deletes its items', function () {
    $item = OrderItem::factory()->create();

    $item->order->delete();

    expect(OrderItem::query()->count())->toBe(0);
});

test('the same ticket type cannot appear twice in one order', function () {
    $order = Order::factory()->create();
    $ticketType = TicketType::factory()->for($order->event)->create();
    OrderItem::factory()->for($order)->for($ticketType)->create();

    expect(fn () => OrderItem::factory()->for($order)->for($ticketType)->create())
        ->toThrow(QueryException::class);
});
