<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;

test('ticket types are on sale only within the sales period', function (string $state, bool $expected) {
    $ticketType = TicketType::factory()->{$state}()->create();

    expect($ticketType->isOnSale())->toBe($expected);
})->with([
    'before the sales period' => ['notOnSaleYet', false],
    'after the sales period' => ['salesEnded', false],
]);

test('ticket types are on sale during the sales period', function () {
    expect(TicketType::factory()->create()->isOnSale())->toBeTrue();
});

test('the sales end is exclusive', function () {
    $this->freezeTime();

    $ticketType = TicketType::factory()->create([
        'sales_starts_at' => now()->subDay(),
        'sales_ends_at' => now(),
    ]);

    expect($ticketType->isOnSale())->toBeFalse();
});

test('remaining tickets exclude paid orders and recent pending orders', function () {
    $ticketType = TicketType::factory()->create(['capacity' => 10]);

    OrderItem::factory()->for(Order::factory()->paid())->for($ticketType)->create(['quantity' => 3]);
    OrderItem::factory()->for(Order::factory())->for($ticketType)->create(['quantity' => 2]);

    expect($ticketType->remaining())->toBe(5);
});

test('expired pending orders and canceled orders do not reserve tickets', function () {
    $ticketType = TicketType::factory()->create(['capacity' => 10]);

    OrderItem::factory()->for(Order::factory())->for($ticketType)->create(['quantity' => 2]);
    OrderItem::factory()->for(Order::factory()->canceled())->for($ticketType)->create(['quantity' => 4]);

    $this->travel(TicketType::PENDING_MINUTES + 1)->minutes();

    expect($ticketType->remaining())->toBe(10);
});

test('remaining tickets never go below zero', function () {
    $ticketType = TicketType::factory()->create(['capacity' => 2]);

    OrderItem::factory()->for(Order::factory()->paid())->for($ticketType)->create(['quantity' => 3]);

    expect($ticketType->remaining())->toBe(0);
});

test('a pending order created exactly 15 minutes ago still reserves tickets', function () {
    $this->freezeTime();

    $ticketType = TicketType::factory()->create(['capacity' => 10]);
    $order = Order::factory()->create(['created_at' => now()->subMinutes(TicketType::PENDING_MINUTES)]);
    OrderItem::factory()->for($order)->for($ticketType)->create(['quantity' => 3]);

    expect($ticketType->remaining())->toBe(7);
});

test('a pending order created 15 minutes and 1 second ago does not reserve tickets', function () {
    $this->freezeTime();

    $ticketType = TicketType::factory()->create(['capacity' => 10]);
    $order = Order::factory()->create(['created_at' => now()->subMinutes(TicketType::PENDING_MINUTES)->subSecond()]);
    OrderItem::factory()->for($order)->for($ticketType)->create(['quantity' => 3]);

    expect($ticketType->remaining())->toBe(10);
});

test('paid orders reserve tickets no matter how old they are', function () {
    $ticketType = TicketType::factory()->create(['capacity' => 10]);
    $order = Order::factory()->paid()->create(['created_at' => now()->subDays(3)]);
    OrderItem::factory()->for($order)->for($ticketType)->create(['quantity' => 3]);

    expect($ticketType->remaining())->toBe(7);
});

test('orders for other ticket types do not reduce remaining tickets', function () {
    $ticketType = TicketType::factory()->create(['capacity' => 10]);
    $otherTicketType = TicketType::factory()->create(['capacity' => 10]);

    OrderItem::factory()->for(Order::factory()->paid())->for($otherTicketType)->create(['quantity' => 4]);

    expect($ticketType->remaining())->toBe(10);
});
