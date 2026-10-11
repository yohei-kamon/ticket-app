<?php

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
