<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Carbon;

test('guests are redirected to the login page', function () {
    $this->get(route('my.orders.index'))->assertRedirect(route('login'));
});

test('the order list shows only the current user\'s orders', function () {
    $user = User::factory()->create();
    Order::factory()->for($user)->for(Event::factory()->published()->create(['title' => '自分のイベント']))->create();
    Order::factory()->for(Event::factory()->published()->create(['title' => '他人のイベント']))->create();

    $this->actingAs($user)
        ->get(route('my.orders.index'))
        ->assertOk()
        ->assertSee('自分のイベント')
        ->assertDontSee('他人のイベント');
});

test('the order detail of another user\'s order is forbidden', function () {
    $order = Order::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('my.orders.show', $order))
        ->assertForbidden();
});

test('the order detail of a paid order lists its ticket codes', function () {
    $order = Order::factory()->paid()->create();
    $item = OrderItem::factory()->for($order)->create(['quantity' => 1]);
    $ticket = Ticket::factory()->for($item)->create();

    $this->actingAs($order->user)
        ->get(route('my.orders.show', $order))
        ->assertOk()
        ->assertSee($item->ticketType->name)
        ->assertSee($ticket->code);
});

test('the order detail of a pending order says the payment is not complete', function () {
    $order = Order::factory()->create();
    OrderItem::factory()->for($order)->create();

    $this->actingAs($order->user)
        ->get(route('my.orders.show', $order))
        ->assertOk()
        ->assertSee('支払いが完了していません');
});

test('the organizer of the event can open the order detail', function () {
    $organizer = User::factory()->organizer()->create();
    $order = Order::factory()->for(Event::factory()->published()->for($organizer, 'organizer')->create())->create();
    OrderItem::factory()->for($order)->create();

    $this->actingAs($organizer)->get(route('my.orders.show', $order))->assertOk();
});

test('an organizer of another event cannot open the order detail', function () {
    $order = Order::factory()->create();

    $this->actingAs(User::factory()->organizer()->create())
        ->get(route('my.orders.show', $order))
        ->assertForbidden();
});

test('an admin can open the order detail', function () {
    $order = Order::factory()->create();
    OrderItem::factory()->for($order)->create();

    $this->actingAs(User::factory()->admin()->create())->get(route('my.orders.show', $order))->assertOk();
});

test('guests are redirected to the login page from the order detail', function () {
    $this->get(route('my.orders.show', Order::factory()->create()))->assertRedirect(route('login'));
});

test('the order detail of a canceled order says it was canceled and shows no tickets', function () {
    $order = Order::factory()->canceled()->create();
    $item = OrderItem::factory()->for($order)->create(['quantity' => 1]);
    $ticket = Ticket::factory()->for($item)->create();

    $this->actingAs($order->user)
        ->get(route('my.orders.show', $order))
        ->assertOk()
        ->assertSee('この注文は取り消されています')
        ->assertDontSee('支払いが完了していません')
        ->assertDontSee($ticket->code);
});

test('the order detail does not show tickets for a pending order', function () {
    $order = Order::factory()->create();
    $item = OrderItem::factory()->for($order)->create(['quantity' => 1]);
    $ticket = Ticket::factory()->for($item)->create();

    $this->actingAs($order->user)
        ->get(route('my.orders.show', $order))
        ->assertOk()
        ->assertSee('支払いが完了していません')
        ->assertDontSee($ticket->code);
});

test('the order detail uses the saved unit price instead of the current ticket price', function () {
    $order = Order::factory()->paid()->create(['total_amount' => 2400]);
    $item = OrderItem::factory()->for($order)->create(['quantity' => 3, 'unit_price' => 800]);
    $item->ticketType->update(['price' => 9900]);

    $this->actingAs($order->user)
        ->get(route('my.orders.show', $order))
        ->assertOk()
        ->assertSee('800円')
        ->assertSee('2,400円')
        ->assertDontSee('9,900円')
        ->assertDontSee('29,700円');
});

test('the order list shows newest orders first', function () {
    $user = User::factory()->create();
    $old = Order::factory()->for($user)->for(Event::factory()->published()->create(['title' => '古い注文イベント']))->create(['created_at' => Carbon::now()->subDays(3)]);
    $new = Order::factory()->for($user)->for(Event::factory()->published()->create(['title' => '新しい注文イベント']))->create(['created_at' => Carbon::now()->subDay()]);

    $this->actingAs($user)
        ->get(route('my.orders.index'))
        ->assertSeeInOrder(['新しい注文イベント', '古い注文イベント']);
});

test('the order list is split into pages of ten', function () {
    $user = User::factory()->create();
    foreach (range(1, 11) as $i) {
        Order::factory()->for($user)->for(Event::factory()->published()->create(['title' => "イベント番号{$i}ZZ"]))
            ->create(['created_at' => Carbon::now()->subMinutes($i)]);
    }

    $this->actingAs($user)->get(route('my.orders.index'))
        ->assertSee('イベント番号10ZZ')
        ->assertDontSee('イベント番号11ZZ')
        ->assertSeeHtml('gotoPage(2');

    $this->actingAs($user)->get(route('my.orders.index', ['page' => 2]))
        ->assertSee('イベント番号11ZZ')
        ->assertDontSee('イベント番号1ZZ');
});

test('the order list shows total seats, amount and status label', function () {
    $order = Order::factory()->paid()->create(['total_amount' => 3000]);
    OrderItem::factory()->for($order)->create(['quantity' => 2]);
    OrderItem::factory()->for($order)->create(['quantity' => 3]);

    $this->actingAs($order->user)
        ->get(route('my.orders.index'))
        ->assertSee('5枚')
        ->assertSee('3,000円')
        ->assertSee($order->status->label());
});

test('the order list shows a message when there are no orders', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('my.orders.index'))
        ->assertSee('まだ注文がありません');
});
