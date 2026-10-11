<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Models\User;
use Livewire\Livewire;

test('the sales end defaults to the event start', function () {
    $event = Event::factory()->create();

    $this->actingAs($event->organizer);

    Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->assertSet('ticketTypeSalesEndsAt', $event->starts_at->format('Y-m-d\TH:i'))
        ->set('ticketTypeName', '一般')
        ->set('ticketTypePrice', '3000')
        ->set('ticketTypeCapacity', '100')
        ->set('ticketTypeSalesStartsAt', '2026-10-01T10:00')
        ->set('ticketTypeSalesEndsAt', '2026-10-20T10:00')
        ->call('saveTicketType')
        ->assertHasNoErrors()
        ->assertSet('ticketTypeSalesEndsAt', $event->starts_at->format('Y-m-d\TH:i'));
});

test('organizers can add a ticket type', function () {
    $event = Event::factory()->create();

    $this->actingAs($event->organizer);

    Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->set('ticketTypeName', '一般')
        ->set('ticketTypePrice', '3000')
        ->set('ticketTypeCapacity', '100')
        ->set('ticketTypeSalesStartsAt', '2026-10-01T10:00')
        ->set('ticketTypeSalesEndsAt', '2026-10-20T10:00')
        ->call('saveTicketType')
        ->assertHasNoErrors()
        ->assertSee('3,000円');

    $ticketType = $event->ticketTypes()->sole();

    expect($ticketType->name)->toBe('一般')
        ->and($ticketType->price)->toBe(3000)
        ->and($ticketType->capacity)->toBe(100)
        ->and($ticketType->sales_starts_at->format('Y-m-d H:i'))->toBe('2026-10-01 10:00');
});

test('organizers can edit a ticket type with the same form', function () {
    $ticketType = TicketType::factory()->create(['name' => '一般', 'price' => 3000]);

    $this->actingAs($ticketType->event->organizer);

    Livewire::test('pages::organizer.events.edit', ['event' => $ticketType->event])
        ->call('editTicketType', $ticketType->id)
        ->assertSet('editingTicketTypeId', $ticketType->id)
        ->assertSet('ticketTypeName', '一般')
        ->assertSet('ticketTypePrice', 3000)
        ->set('ticketTypeName', '学生')
        ->set('ticketTypePrice', '1500')
        ->call('saveTicketType')
        ->assertHasNoErrors()
        ->assertSet('editingTicketTypeId', null);

    expect(TicketType::count())->toBe(1)
        ->and($ticketType->refresh()->name)->toBe('学生')
        ->and($ticketType->price)->toBe(1500);
});

test('organizers can delete a ticket type', function () {
    $ticketType = TicketType::factory()->create();

    $this->actingAs($ticketType->event->organizer);

    Livewire::test('pages::organizer.events.edit', ['event' => $ticketType->event])
        ->call('deleteTicketType', $ticketType->id);

    expect(TicketType::count())->toBe(0);
});

test('ticket types with orders cannot be deleted from the edit screen', function () {
    $item = OrderItem::factory()->create();
    $event = $item->ticketType->event;

    $this->actingAs($event->organizer);

    Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->call('deleteTicketType', $item->ticket_type_id);

    expect($item->ticketType->fresh())->not->toBeNull();
});

test('the last ticket type of a published event cannot be deleted', function () {
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create();

    $this->actingAs($event->organizer);

    Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->call('deleteTicketType', $ticketType->id);

    expect($ticketType->fresh())->not->toBeNull();
});

test('ticket type input is validated', function (array $input, string $field, string $rule) {
    $event = Event::factory()->create();

    $this->actingAs($event->organizer);

    $valid = [
        'ticketTypeName' => '一般',
        'ticketTypePrice' => '3000',
        'ticketTypeCapacity' => '100',
        'ticketTypeSalesStartsAt' => '2026-10-01T10:00',
        'ticketTypeSalesEndsAt' => '2026-10-20T10:00',
    ];

    $component = Livewire::test('pages::organizer.events.edit', ['event' => $event]);

    foreach ([...$valid, ...$input] as $name => $value) {
        $component->set($name, $value);
    }

    $component->call('saveTicketType')->assertHasErrors([$field => $rule]);

    expect(TicketType::count())->toBe(0);
})->with([
    'name is required' => [['ticketTypeName' => ''], 'ticketTypeName', 'required'],
    'name is at most 50 characters' => [['ticketTypeName' => str_repeat('あ', 51)], 'ticketTypeName', 'max'],
    'price is required' => [['ticketTypePrice' => ''], 'ticketTypePrice', 'required'],
    'price is an integer' => [['ticketTypePrice' => '100.5'], 'ticketTypePrice', 'integer'],
    'price is at least 0' => [['ticketTypePrice' => '-1'], 'ticketTypePrice', 'min'],
    'capacity is required' => [['ticketTypeCapacity' => ''], 'ticketTypeCapacity', 'required'],
    'capacity is at least 1' => [['ticketTypeCapacity' => '0'], 'ticketTypeCapacity', 'min'],
    'sales end must be after sales start' => [['ticketTypeSalesEndsAt' => '2026-10-01T10:00'], 'ticketTypeSalesEndsAt', 'after'],
]);

test('ticket type actions are forbidden after ownership changes', function (string $method, bool $withId) {
    $ticketType = TicketType::factory()->create(['name' => '一般']);
    $event = $ticketType->event;

    $this->actingAs($event->organizer);

    $component = Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->set('ticketTypeName', '書き換え')
        ->set('ticketTypePrice', '0')
        ->set('ticketTypeCapacity', '1')
        ->set('ticketTypeSalesStartsAt', '2026-10-01T10:00')
        ->set('ticketTypeSalesEndsAt', '2026-10-20T10:00');

    $event->organizer_id = User::factory()->organizer()->create()->id;
    $event->save();

    $component->call($method, ...($withId ? [$ticketType->id] : []))->assertForbidden();

    expect(TicketType::count())->toBe(1)
        ->and($ticketType->refresh()->name)->toBe('一般');
})->with([
    'saveTicketType' => ['saveTicketType', false],
    'editTicketType' => ['editTicketType', true],
    'cancelEditTicketType' => ['cancelEditTicketType', false],
    'deleteTicketType' => ['deleteTicketType', true],
]);

test('ticket types of another event cannot be edited or deleted', function (string $method) {
    $event = Event::factory()->create();
    $otherTicketType = TicketType::factory()->create();

    $this->actingAs($event->organizer);

    Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->call($method, $otherTicketType->id)
        ->assertNotFound();

    expect($otherTicketType->fresh())->not->toBeNull();
})->with(['editTicketType', 'deleteTicketType']);

test('events without ticket types cannot be published', function () {
    $event = Event::factory()->create();

    $this->actingAs($event->organizer);

    Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->assertSee('券種を1つ以上登録すると公開できます')
        ->call('togglePublish');

    expect($event->refresh()->status)->toBe(EventStatus::Draft);
});

test('other organizers cannot open the edit component to add a ticket type', function () {
    $event = Event::factory()->create();

    $this->actingAs(User::factory()->organizer()->create());

    Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->assertForbidden();

    expect($event->ticketTypes()->count())->toBe(0);
});
