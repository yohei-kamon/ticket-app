<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function (string $routeName) {
    $event = Event::factory()->create();

    $this->get(route($routeName, $routeName === 'organizer.events.edit' ? $event : []))
        ->assertRedirect(route('login'));
})->with(['organizer.events.index', 'organizer.events.create', 'organizer.events.edit']);

test('customers cannot access organizer event screens', function (string $routeName) {
    $event = Event::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route($routeName, $routeName === 'organizer.events.edit' ? $event : []))
        ->assertForbidden();
})->with(['organizer.events.index', 'organizer.events.create', 'organizer.events.edit']);

test('index lists only the organizer own events', function () {
    $organizer = User::factory()->organizer()->create();
    Event::factory()->for($organizer, 'organizer')->published()->create(['title' => '自分のイベント']);
    Event::factory()->create(['title' => '他人のイベント']);

    $this->actingAs($organizer)
        ->get(route('organizer.events.index'))
        ->assertOk()
        ->assertSee('自分のイベント')
        ->assertSee('公開中')
        ->assertDontSee('他人のイベント');
});

test('organizers can create an event as a draft', function () {
    $organizer = User::factory()->organizer()->create();

    $this->actingAs($organizer);

    Livewire::test('pages::organizer.events.create')
        ->set('title', '夏祭り')
        ->set('description', '盆踊りと屋台')
        ->set('venue', '中央公園')
        ->set('starts_at', '2027-08-01T18:00')
        ->set('ends_at', '2027-08-01T21:00')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $event = Event::sole();

    expect($event->organizer_id)->toBe($organizer->id)
        ->and($event->status)->toBe(EventStatus::Draft)
        ->and($event->starts_at->format('Y-m-d H:i'))->toBe('2027-08-01 18:00');
});

test('event input is validated', function (array $input, string $field, string $rule) {
    $this->actingAs(User::factory()->organizer()->create());

    $valid = [
        'title' => '夏祭り',
        'description' => '盆踊りと屋台',
        'venue' => '中央公園',
        'starts_at' => '2027-08-01T18:00',
        'ends_at' => '2027-08-01T21:00',
    ];

    $component = Livewire::test('pages::organizer.events.create');

    foreach ([...$valid, ...$input] as $name => $value) {
        $component->set($name, $value);
    }

    $component->call('save')->assertHasErrors([$field => $rule]);

    expect(Event::count())->toBe(0);
})->with([
    'title is required' => [['title' => ''], 'title', 'required'],
    'description is required' => [['description' => ''], 'description', 'required'],
    'venue is required' => [['venue' => ''], 'venue', 'required'],
    'starts_at is required' => [['starts_at' => ''], 'starts_at', 'required'],
    'ends_at is required' => [['ends_at' => ''], 'ends_at', 'required'],
    'title is at most 100 characters' => [['title' => str_repeat('あ', 101)], 'title', 'max'],
    'venue is at most 100 characters' => [['venue' => str_repeat('あ', 101)], 'venue', 'max'],
    'ends_at must be after starts_at' => [['ends_at' => '2027-08-01T18:00'], 'ends_at', 'after'],
]);

test('create save is forbidden when the user is no longer an organizer', function () {
    $user = User::factory()->organizer()->create();

    $this->actingAs($user);

    $component = Livewire::test('pages::organizer.events.create')
        ->set('title', '夏祭り')
        ->set('description', '盆踊りと屋台')
        ->set('venue', '中央公園')
        ->set('starts_at', '2027-08-01T18:00')
        ->set('ends_at', '2027-08-01T21:00');

    $user->update(['role' => 'customer']);

    $component->call('save')->assertForbidden();

    expect(Event::count())->toBe(0);
});

test('organizers cannot open the edit screen of another organizer event', function () {
    $event = Event::factory()->create();

    $this->actingAs(User::factory()->organizer()->create())
        ->get(route('organizer.events.edit', $event))
        ->assertForbidden();
});

test('organizers can update their event', function () {
    $event = Event::factory()->create();

    $this->actingAs($event->organizer);

    Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->assertSet('starts_at', $event->starts_at->format('Y-m-d\TH:i'))
        ->set('title', '新しいタイトル')
        ->call('save')
        ->assertHasNoErrors();

    expect($event->refresh()->title)->toBe('新しいタイトル');
});

test('changing methods are forbidden after ownership changes', function (string $method) {
    $event = Event::factory()->create(['title' => '元のタイトル']);

    $this->actingAs($event->organizer);

    $component = Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->set('title', '書き換え');

    $event->organizer_id = User::factory()->organizer()->create()->id;
    $event->save();

    $component->call($method)->assertForbidden();

    $event->refresh();

    expect($event->title)->toBe('元のタイトル')
        ->and($event->status)->toBe(EventStatus::Draft);
})->with(['save', 'togglePublish', 'deleteEvent']);

test('organizers can toggle publish status', function () {
    $event = Event::factory()->create();
    TicketType::factory()->for($event)->create();

    $this->actingAs($event->organizer);

    $component = Livewire::test('pages::organizer.events.edit', ['event' => $event]);

    $component->call('togglePublish');
    expect($event->refresh()->status)->toBe(EventStatus::Published);

    $component->call('togglePublish');
    expect($event->refresh()->status)->toBe(EventStatus::Draft);
});

test('organizers cannot republish an event unpublished by an admin', function () {
    $event = Event::factory()->unpublished()->create();

    $this->actingAs($event->organizer);

    Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->assertSee('管理者により非公開にされています')
        ->assertDontSee('公開する')
        ->call('togglePublish')
        ->assertForbidden();

    expect($event->refresh()->status)->toBe(EventStatus::Unpublished);
});

test('organizers can delete their event', function () {
    $event = Event::factory()->create();

    $this->actingAs($event->organizer);

    Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->call('deleteEvent')
        ->assertRedirect(route('organizer.events.index'));

    expect(Event::count())->toBe(0);
});

test('organizers cannot mount the edit component of another organizer event', function () {
    $event = Event::factory()->create();

    $this->actingAs(User::factory()->organizer()->create());

    Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->assertForbidden();
});

test('create is rejected when ends_at is before starts_at', function () {
    $this->actingAs(User::factory()->organizer()->create());

    Livewire::test('pages::organizer.events.create')
        ->set('title', '夏祭り')
        ->set('description', '盆踊りと屋台')
        ->set('venue', '中央公園')
        ->set('starts_at', '2027-08-01T18:00')
        ->set('ends_at', '2027-08-01T17:00')
        ->call('save')
        ->assertHasErrors(['ends_at' => 'after']);

    expect(Event::count())->toBe(0);
});

test('update is rejected when ends_at is before starts_at', function () {
    $event = Event::factory()->create();
    $originalEndsAt = $event->ends_at->copy();

    $this->actingAs($event->organizer);

    Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->set('starts_at', '2027-08-01T18:00')
        ->set('ends_at', '2027-08-01T17:00')
        ->call('save')
        ->assertHasErrors(['ends_at' => 'after']);

    expect($event->refresh()->ends_at->equalTo($originalEndsAt))->toBeTrue();
});
