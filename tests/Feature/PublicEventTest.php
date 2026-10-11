<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Models\User;

test('the event list shows only published events that have not ended', function () {
    Event::factory()->published()->create(['title' => '公開中のイベント']);
    Event::factory()->create(['title' => '下書きのイベント']);
    Event::factory()->unpublished()->create(['title' => '非公開のイベント']);
    Event::factory()->published()->past()->create(['title' => '終了したイベント']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('公開中のイベント')
        ->assertDontSee('下書きのイベント')
        ->assertDontSee('非公開のイベント')
        ->assertDontSee('終了したイベント');
});

test('events that have started but not ended are still listed', function () {
    Event::factory()->published()->create([
        'title' => '開催中のイベント',
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
    ]);

    $this->get(route('home'))->assertSee('開催中のイベント');
});

test('the event list is ordered by start time', function () {
    Event::factory()->published()->create(['title' => '後のイベント', 'starts_at' => now()->addWeeks(2), 'ends_at' => now()->addWeeks(2)->addHour()]);
    Event::factory()->published()->create(['title' => '先のイベント', 'starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addHour()]);

    $this->get(route('home'))->assertSeeInOrder(['先のイベント', '後のイベント']);
});

test('the event list shows the lowest price or free', function () {
    $paidEvent = Event::factory()->published()->create();
    TicketType::factory()->for($paidEvent)->create(['price' => 3000]);
    TicketType::factory()->for($paidEvent)->create(['price' => 1500]);

    $freeEvent = Event::factory()->published()->create();
    TicketType::factory()->for($freeEvent)->create(['price' => 0]);

    $this->get(route('home'))
        ->assertSee('1,500円〜')
        ->assertDontSee('3,000円〜')
        ->assertSee('無料');
});

test('the event list is paginated by 12', function () {
    foreach (range(1, 13) as $day) {
        Event::factory()->published()->create([
            'title' => "イベント{$day}番",
            'starts_at' => now()->addDays($day),
            'ends_at' => now()->addDays($day)->addHour(),
        ]);
    }

    $this->get(route('home'))->assertSee('イベント12番')->assertDontSee('イベント13番');
    $this->get(route('home', ['page' => 2]))->assertSee('イベント13番')->assertDontSee('イベント12番');
});

test('guests can view a published event with its ticket types', function () {
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create(['name' => '一般', 'price' => 3000, 'capacity' => 10]);
    OrderItem::factory()->for(Order::factory()->paid()->for($event))->for($ticketType)->create(['quantity' => 4]);

    $this->get(route('events.show', $event))
        ->assertOk()
        ->assertSee($event->title)
        ->assertSee($event->venue)
        ->assertSee($event->organizer->name)
        ->assertSee('3,000円')
        ->assertSee('残り 6枚');
});

test('sold out ticket types are marked', function () {
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create(['capacity' => 2]);
    OrderItem::factory()->for(Order::factory()->paid()->for($event))->for($ticketType)->create(['quantity' => 2]);

    $this->get(route('events.show', $event))->assertSee('売り切れ');
});

test('draft events are not found for guests and other users', function () {
    $event = Event::factory()->create();

    $this->get(route('events.show', $event))->assertNotFound();

    $this->actingAs(User::factory()->organizer()->create())
        ->get(route('events.show', $event))
        ->assertNotFound();
});

test('draft events can be viewed by the owner and admins', function () {
    $event = Event::factory()->create();

    $this->actingAs($event->organizer)->get(route('events.show', $event))->assertOk();
    $this->actingAs(User::factory()->admin()->create())->get(route('events.show', $event))->assertOk();
});

test('draft events are not found for customers', function () {
    $event = Event::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('events.show', $event))
        ->assertNotFound();
});

test('unpublished events are not found for guests, customers and other organizers', function () {
    $event = Event::factory()->unpublished()->create();

    $this->get(route('events.show', $event))->assertNotFound();
    $this->actingAs(User::factory()->create())->get(route('events.show', $event))->assertNotFound();
    $this->actingAs(User::factory()->organizer()->create())->get(route('events.show', $event))->assertNotFound();
});

test('unpublished events can be viewed by the owner and admins', function () {
    $event = Event::factory()->unpublished()->create();

    $this->actingAs($event->organizer)->get(route('events.show', $event))->assertOk();
    $this->actingAs(User::factory()->admin()->create())->get(route('events.show', $event))->assertOk();
});
