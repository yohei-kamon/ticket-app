<?php

use App\Enums\OrderStatus;
use App\Exceptions\PurchaseException;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Models\User;
use App\Services\OrderService;

test('orders are created as pending with items priced at the time of the order', function () {
    $user = User::factory()->create();
    $event = Event::factory()->published()->create();
    $general = TicketType::factory()->for($event)->create(['price' => 3000, 'capacity' => 10]);
    $student = TicketType::factory()->for($event)->create(['price' => 1500, 'capacity' => 10]);

    $order = app(OrderService::class)->createOrder($user, $event, [$general->id => 2, $student->id => 1]);

    expect($order->refresh()->status)->toBe(OrderStatus::Pending)
        ->and($order->user_id)->toBe($user->id)
        ->and($order->event_id)->toBe($event->id)
        ->and($order->total_amount)->toBe(7500)
        ->and($order->items()->orderBy('ticket_type_id')->get(['ticket_type_id', 'quantity', 'unit_price'])->toArray())->toBe([
            ['ticket_type_id' => $general->id, 'quantity' => 2, 'unit_price' => 3000],
            ['ticket_type_id' => $student->id, 'quantity' => 1, 'unit_price' => 1500],
        ]);
});

test('orders are not created when the ticket type cannot be purchased', function (Closure $makeTicketType, int $quantity, string $message) {
    $event = Event::factory()->published()->create();
    $ticketType = $makeTicketType($event);

    expect(fn () => app(OrderService::class)->createOrder(User::factory()->create(), $event, [$ticketType->id => $quantity]))
        ->toThrow(PurchaseException::class, $message);

    expect(Order::query()->count())->toBe(0)
        ->and(OrderItem::query()->count())->toBe(0);
})->with([
    'more than remaining' => [
        fn (Event $event) => TicketType::factory()->for($event)->create(['name' => '一般', 'capacity' => 2]),
        3,
        '「一般」の残りは2枚です。',
    ],
    'outside the sales period' => [
        fn (Event $event) => TicketType::factory()->for($event)->salesEnded()->create(['name' => '一般']),
        1,
        '「一般」は販売期間外です。',
    ],
    'more than the maximum quantity' => [
        fn (Event $event) => TicketType::factory()->for($event)->create(['name' => '一般', 'capacity' => 100]),
        OrderService::MAX_QUANTITY + 1,
        '「一般」は1〜10枚の範囲で選んでください。',
    ],
]);

test('orders are not created for events that cannot be purchased', function (Closure $makeEvent) {
    $event = $makeEvent();
    $ticketType = TicketType::factory()->for($event)->create(['sales_ends_at' => now()->addMonth()]);

    expect(fn () => app(OrderService::class)->createOrder(User::factory()->create(), $event, [$ticketType->id => 1]))
        ->toThrow(PurchaseException::class, 'このイベントのチケットは購入できません。');

    expect(Order::query()->count())->toBe(0);
})->with([
    'draft' => fn () => Event::factory()->create(),
    'unpublished by an admin' => fn () => Event::factory()->unpublished()->create(),
    'already started' => fn () => Event::factory()->published()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]),
]);

test('orders are not created for invalid quantities', function (Closure $makeQuantities, string $message) {
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create(['name' => '一般', 'capacity' => 100]);

    expect(fn () => app(OrderService::class)->createOrder(User::factory()->create(), $event, $makeQuantities($ticketType)))
        ->toThrow(PurchaseException::class, $message);

    expect(Order::query()->count())->toBe(0);
})->with([
    'nothing selected' => [fn (TicketType $ticketType) => [], 'チケットの枚数を選んでください。'],
    'zero tickets' => [fn (TicketType $ticketType) => [$ticketType->id => 0], '「一般」は1〜10枚の範囲で選んでください。'],
    'not an integer' => [fn (TicketType $ticketType) => [$ticketType->id => 'abc'], '「一般」は1〜10枚の範囲で選んでください。'],
    'a ticket type of another event' => [fn (TicketType $ticketType) => [TicketType::factory()->create()->id => 1], '選択した券種が見つかりません。'],
]);
