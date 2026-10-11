<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

test('published events can be viewed by anyone', function () {
    $event = Event::factory()->published()->create();

    expect(Gate::forUser(null)->allows('view', $event))->toBeTrue()
        ->and(Gate::forUser(User::factory()->create())->allows('view', $event))->toBeTrue();
});

test('unpublished events can be viewed only by the owner and admins', function (EventStatus $status) {
    $event = Event::factory()->create(['status' => $status]);

    expect(Gate::forUser(null)->allows('view', $event))->toBeFalse()
        ->and(Gate::forUser(User::factory()->create())->allows('view', $event))->toBeFalse()
        ->and(Gate::forUser(User::factory()->organizer()->create())->allows('view', $event))->toBeFalse()
        ->and(Gate::forUser($event->organizer)->allows('view', $event))->toBeTrue()
        ->and(Gate::forUser(User::factory()->admin()->create())->allows('view', $event))->toBeTrue();
})->with([EventStatus::Draft, EventStatus::Unpublished]);

test('only organizers can create events', function () {
    expect(Gate::forUser(User::factory()->organizer()->create())->allows('create', Event::class))->toBeTrue()
        ->and(Gate::forUser(User::factory()->create())->allows('create', Event::class))->toBeFalse()
        ->and(Gate::forUser(User::factory()->admin()->create())->allows('create', Event::class))->toBeFalse();
});

test('only the owner can publish events that an admin has not unpublished', function (EventStatus $status, bool $ownerCanPublish) {
    $event = Event::factory()->create(['status' => $status]);

    expect(Gate::forUser($event->organizer)->allows('publish', $event))->toBe($ownerCanPublish)
        ->and(Gate::forUser(User::factory()->organizer()->create())->allows('publish', $event))->toBeFalse();
})->with([
    'draft' => [EventStatus::Draft, true],
    'published' => [EventStatus::Published, true],
    'unpublished' => [EventStatus::Unpublished, false],
]);

test('only admins can unpublish events', function () {
    $event = Event::factory()->published()->create();

    expect(Gate::forUser(User::factory()->admin()->create())->allows('unpublish', $event))->toBeTrue()
        ->and(Gate::forUser($event->organizer)->allows('unpublish', $event))->toBeFalse();
});

test('published and upcoming scopes return only future published events', function () {
    $target = Event::factory()->published()->create();
    Event::factory()->published()->past()->create();
    Event::factory()->create();
    Event::factory()->unpublished()->create();

    expect(Event::published()->upcoming()->pluck('id')->all())->toBe([$target->id]);
});

test('upcoming scope compares start times in Japan time', function () {
    $event = Event::factory()->published()->create([
        'starts_at' => '2027-08-01 18:00',
        'ends_at' => '2027-08-01 21:00',
    ]);

    $this->travelTo(CarbonImmutable::parse('2027-08-01 17:59', 'Asia/Tokyo'));
    expect(Event::upcoming()->pluck('id')->all())->toBe([$event->id]);

    $this->travelTo(CarbonImmutable::parse('2027-08-01 18:30', 'Asia/Tokyo'));
    expect(Event::upcoming()->exists())->toBeFalse();
});
