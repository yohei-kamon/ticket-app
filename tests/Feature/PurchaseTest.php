<?php

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Models\User;
use App\Services\CheckoutService;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Stripe\Exception\ApiConnectionException;

test('only ticket types on sale and in stock have a quantity select limited by the remaining tickets', function () {
    $event = Event::factory()->published()->create();
    $few = TicketType::factory()->for($event)->create(['name' => '残りわずか', 'capacity' => 3]);
    $soldOut = TicketType::factory()->for($event)->create(['name' => '売り切れ券', 'capacity' => 1]);
    OrderItem::factory()->for(Order::factory()->paid()->for($event))->for($soldOut)->create(['quantity' => 1]);
    $ended = TicketType::factory()->for($event)->salesEnded()->create(['name' => '終了券', 'capacity' => 50]);

    Livewire::test('pages::events.show', ['event' => $event])
        ->assertSeeHtml('wire:model="quantities.'.$few->id.'"')
        ->assertSee('3枚')
        ->assertDontSee('4枚')
        ->assertDontSeeHtml('wire:model="quantities.'.$soldOut->id.'"')
        ->assertDontSeeHtml('wire:model="quantities.'.$ended->id.'"')
        ->assertSee('購入手続きへ進む');
});

test('selected quantities are kept in the session before moving on to the purchase page', function () {
    $event = Event::factory()->published()->create();
    $general = TicketType::factory()->for($event)->create();
    $student = TicketType::factory()->for($event)->create();

    Livewire::test('pages::events.show', ['event' => $event])
        ->set("quantities.{$general->id}", '2')
        ->set("quantities.{$student->id}", '0')
        ->call('proceed')
        ->assertRedirect(route('events.purchase', $event));

    expect(session("purchase.{$event->id}"))->toBe([$general->id => 2]);
});

test('proceeding without any ticket shows an error', function () {
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create();

    Livewire::test('pages::events.show', ['event' => $event])
        ->set("quantities.{$ticketType->id}", '0')
        ->call('proceed')
        ->assertHasErrors('quantities')
        ->assertNoRedirect();
});

test('guests are sent back to the purchase page after logging in', function () {
    $event = Event::factory()->published()->create();
    $user = User::factory()->create();

    $this->get(route('events.purchase', $event))->assertRedirect(route('login'));

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('events.purchase', $event));
});

test('the purchase page shows subtotals and the total', function () {
    $event = Event::factory()->published()->create();
    $general = TicketType::factory()->for($event)->create(['price' => 3000]);
    $student = TicketType::factory()->for($event)->create(['price' => 1500]);

    $this->actingAs(User::factory()->create())
        ->withSession(["purchase.{$event->id}" => [$general->id => 2, $student->id => 1]])
        ->get(route('events.purchase', $event))
        ->assertOk()
        ->assertSee('6,000円')
        ->assertSee('7,500円');
});

test('the purchase page sends users back to the event when nothing is selected', function () {
    $event = Event::factory()->published()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('events.purchase', $event))
        ->assertRedirect(route('events.show', $event));
});

test('draft events cannot be purchased', function () {
    $event = Event::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('events.purchase', $event))
        ->assertNotFound();
});

test('checking out creates a pending order and redirects to the Stripe payment page', function () {
    $user = User::factory()->create();
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create(['price' => 3000]);
    session()->put("purchase.{$event->id}", [$ticketType->id => 2]);

    $this->mock(CheckoutService::class)
        ->shouldReceive('createSession')
        ->once()
        ->withArgs(fn (Order $order): bool => $order->total_amount === 6000)
        ->andReturn('https://checkout.stripe.com/c/pay/cs_test_dummy');

    $this->actingAs($user);

    Livewire::test('pages::events.purchase', ['event' => $event])
        ->call('checkout')
        ->assertHasNoErrors()
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_dummy');

    $order = Order::query()->sole();

    expect($order->user_id)->toBe($user->id)
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and($order->total_amount)->toBe(6000)
        ->and(session()->has("purchase.{$event->id}"))->toBeFalse();
});

test('free orders skip Stripe and move on to the completion page', function () {
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create(['price' => 0]);
    session()->put("purchase.{$event->id}", [$ticketType->id => 1]);

    $this->mock(CheckoutService::class)->shouldNotReceive('createSession');

    $this->actingAs(User::factory()->create());

    $component = Livewire::test('pages::events.purchase', ['event' => $event])->call('checkout');

    $order = Order::query()->sole();

    $component->assertHasNoErrors()->assertRedirect(route('orders.complete', $order));

    expect($order->status)->toBe(OrderStatus::Pending);
});

test('the order is canceled when the Stripe payment page cannot be created', function () {
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create(['price' => 3000]);
    session()->put("purchase.{$event->id}", [$ticketType->id => 1]);

    $this->mock(CheckoutService::class)
        ->shouldReceive('createSession')
        ->andThrow(new ApiConnectionException('Could not connect to Stripe.'));

    $this->actingAs(User::factory()->create());

    Livewire::test('pages::events.purchase', ['event' => $event])
        ->call('checkout')
        ->assertHasErrors('purchase')
        ->assertSee('決済ページを開けませんでした。')
        ->assertNoRedirect();

    expect(Order::query()->sole()->status)->toBe(OrderStatus::Canceled);
});

test('purchase errors are shown and no order is created', function () {
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create(['name' => '一般', 'capacity' => 2]);
    session()->put("purchase.{$event->id}", [$ticketType->id => 2]);

    $this->actingAs(User::factory()->create());

    $component = Livewire::test('pages::events.purchase', ['event' => $event]);

    OrderItem::factory()->for(Order::factory()->paid()->for($event))->for($ticketType)->create(['quantity' => 1]);

    $component->call('checkout')
        ->assertHasErrors('purchase')
        ->assertSee('「一般」の残りは1枚です。')
        ->assertNoRedirect();

    expect(Order::query()->count())->toBe(1);
});

test('the completion page can be opened only by those allowed to view the order', function () {
    $order = Order::factory()->create();

    $this->get(route('orders.complete', $order))->assertRedirect(route('login'));

    $this->actingAs($order->user)
        ->get(route('orders.complete', $order))
        ->assertOk()
        ->assertSee('注文を受け付けました（支払いは平日課題で実装）');

    $this->actingAs(User::factory()->create())
        ->get(route('orders.complete', $order))
        ->assertForbidden();

    $this->actingAs(User::factory()->organizer()->create())
        ->get(route('orders.complete', $order))
        ->assertForbidden();
});

test('the event organizer and admins can open the completion page of an order', function () {
    $organizer = User::factory()->organizer()->create();
    $order = Order::factory()->for(Event::factory()->published()->for($organizer, 'organizer'))->create();

    $this->actingAs($organizer)
        ->get(route('orders.complete', $order))
        ->assertOk();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('orders.complete', $order))
        ->assertOk();
});

test('proceeding without touching any select shows an error', function () {
    $event = Event::factory()->published()->create();
    TicketType::factory()->for($event)->create();

    Livewire::test('pages::events.show', ['event' => $event])
        ->call('proceed')
        ->assertHasErrors('quantities')
        ->assertNoRedirect();

    expect(session()->has("purchase.{$event->id}"))->toBeFalse();
});

test('quantities of ticket types from another event are ignored', function () {
    $event = Event::factory()->published()->create();
    TicketType::factory()->for($event)->create();
    $other = TicketType::factory()->for(Event::factory()->published())->create();

    Livewire::test('pages::events.show', ['event' => $event])
        ->set("quantities.{$other->id}", '2')
        ->call('proceed')
        ->assertHasErrors('quantities')
        ->assertNoRedirect();

    expect(session()->has("purchase.{$event->id}"))->toBeFalse();
});

test('events that have already started cannot be purchased', function () {
    $event = Event::factory()->published()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);
    $ticketType = TicketType::factory()->for($event)->create(['sales_ends_at' => now()->addDay()]);

    Livewire::test('pages::events.show', ['event' => $event])
        ->assertDontSeeHtml('wire:model="quantities.'.$ticketType->id.'"')
        ->assertDontSee('購入手続きへ進む');

    $this->actingAs(User::factory()->create())
        ->withSession(["purchase.{$event->id}" => [$ticketType->id => 1]])
        ->get(route('events.purchase', $event))
        ->assertNotFound();
});

test('checking out fails when the event is unpublished after opening the purchase page', function () {
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create();
    session()->put("purchase.{$event->id}", [$ticketType->id => 1]);

    $this->actingAs(User::factory()->create());

    $component = Livewire::test('pages::events.purchase', ['event' => $event]);

    $event->forceFill(['status' => EventStatus::Unpublished])->save();

    $component->call('checkout')
        ->assertHasErrors('purchase')
        ->assertSee('このイベントのチケットは購入できません。')
        ->assertNoRedirect();

    expect(Order::query()->count())->toBe(0);
});

test('the quantities on the purchase page cannot be changed from the browser', function () {
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create();
    session()->put("purchase.{$event->id}", [$ticketType->id => 1]);

    $this->actingAs(User::factory()->create());

    expect(fn () => Livewire::test('pages::events.purchase', ['event' => $event])->set("quantities.{$ticketType->id}", 'abc'))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

test('the order passed to Stripe belongs to the buyer and holds the selected items and unit prices', function () {
    $user = User::factory()->create();
    $event = Event::factory()->published()->create();
    $general = TicketType::factory()->for($event)->create(['price' => 3000]);
    $student = TicketType::factory()->for($event)->create(['price' => 1500]);
    session()->put("purchase.{$event->id}", [$general->id => 2, $student->id => 1]);

    $passedItems = null;

    $this->mock(CheckoutService::class)
        ->shouldReceive('createSession')
        ->once()
        ->withArgs(function (Order $order) use ($user, &$passedItems): bool {
            $passedItems = $order->items()->pluck('unit_price', 'ticket_type_id')->all()
                + ['quantities' => $order->items()->pluck('quantity', 'ticket_type_id')->all()];

            return $order->user_id === $user->id && $order->status === OrderStatus::Pending;
        })
        ->andReturn('https://checkout.stripe.com/c/pay/cs_test_dummy');

    $this->actingAs($user);

    Livewire::test('pages::events.purchase', ['event' => $event])
        ->call('checkout')
        ->assertHasNoErrors()
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_dummy');

    expect($passedItems[$general->id])->toBe(3000)
        ->and($passedItems[$student->id])->toBe(1500)
        ->and($passedItems['quantities'])->toBe([$general->id => 2, $student->id => 1]);
});

test('an order mixing free and paid ticket types goes to Stripe', function () {
    $event = Event::factory()->published()->create();
    $free = TicketType::factory()->for($event)->create(['price' => 0]);
    $paid = TicketType::factory()->for($event)->create(['price' => 2000]);
    session()->put("purchase.{$event->id}", [$free->id => 1, $paid->id => 1]);

    $this->mock(CheckoutService::class)
        ->shouldReceive('createSession')
        ->once()
        ->withArgs(fn (Order $order): bool => $order->total_amount === 2000)
        ->andReturn('https://checkout.stripe.com/c/pay/cs_test_dummy');

    $this->actingAs(User::factory()->create());

    Livewire::test('pages::events.purchase', ['event' => $event])
        ->call('checkout')
        ->assertHasNoErrors()
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_dummy');
});

test('Stripe is not called when the capacity is exceeded', function () {
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create(['name' => '一般', 'capacity' => 2, 'price' => 3000]);
    session()->put("purchase.{$event->id}", [$ticketType->id => 2]);

    $this->mock(CheckoutService::class)->shouldNotReceive('createSession');

    $this->actingAs(User::factory()->create());

    $component = Livewire::test('pages::events.purchase', ['event' => $event]);

    OrderItem::factory()->for(Order::factory()->paid()->for($event))->for($ticketType)->create(['quantity' => 1]);

    $component->call('checkout')
        ->assertHasErrors('purchase')
        ->assertSee('「一般」の残りは1枚です。')
        ->assertNoRedirect();

    expect(Order::query()->count())->toBe(1);
});

test('Stripe is not called when the sales period has ended', function () {
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create(['name' => '一般', 'price' => 3000, 'sales_ends_at' => now()->addHour()]);
    session()->put("purchase.{$event->id}", [$ticketType->id => 1]);

    $this->mock(CheckoutService::class)->shouldNotReceive('createSession');

    $this->actingAs(User::factory()->create());

    $component = Livewire::test('pages::events.purchase', ['event' => $event]);

    $this->travel(2)->hours();

    $component->call('checkout')
        ->assertHasErrors('purchase')
        ->assertSee('「一般」は販売期間外です。')
        ->assertNoRedirect();

    expect(Order::query()->count())->toBe(0);
});

test('the reserved tickets are released when the Stripe payment page cannot be created', function () {
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create(['capacity' => 5, 'price' => 3000]);
    session()->put("purchase.{$event->id}", [$ticketType->id => 3]);

    $this->mock(CheckoutService::class)
        ->shouldReceive('createSession')
        ->andThrow(new ApiConnectionException('Could not connect to Stripe.'));

    $this->actingAs(User::factory()->create());

    Livewire::test('pages::events.purchase', ['event' => $event])
        ->call('checkout')
        ->assertHasErrors('purchase');

    expect($ticketType->remaining())->toBe(5);
});
