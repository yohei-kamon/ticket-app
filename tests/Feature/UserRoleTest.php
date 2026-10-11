<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

test('users can register as an organizer', function () {
    $this->skipUnlessFortifyHas(Features::registration());

    $response = $this->post(route('register.store'), [
        'name' => 'Organizer',
        'email' => 'organizer@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'organizer',
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertAuthenticated();
    expect(User::where('email', 'organizer@example.com')->first()->role)->toBe(UserRole::Organizer);
});

test('users cannot register as an admin', function () {
    $this->skipUnlessFortifyHas(Features::registration());

    $response = $this->post(route('register.store'), [
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'admin',
    ]);

    $response->assertSessionHasErrors('role');
    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'admin@example.com']);
});

test('invalid role error is shown on the registration screen', function () {
    $this->skipUnlessFortifyHas(Features::registration());

    $this->from(route('register'))
        ->followingRedirects()
        ->post(route('register.store'), [
            'name' => 'Someone',
            'email' => 'someone@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'unknown',
        ])
        ->assertSee('選択した 利用区分は 無効です。');
});

test('dashboard redirects users by role', function (UserRole $role, string $path) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect($path);
})->with([
    'admin' => [UserRole::Admin, '/admin/events'],
    'organizer' => [UserRole::Organizer, '/organizer/events'],
    'customer' => [UserRole::Customer, '/my/orders'],
]);

test('role middleware only allows the given roles', function () {
    Route::middleware(['web', 'auth', 'role:organizer'])
        ->get('/_test/organizer-only', fn () => 'ok');

    $this->actingAs(User::factory()->organizer()->create())
        ->get('/_test/organizer-only')
        ->assertOk();

    $this->actingAs(User::factory()->create())
        ->get('/_test/organizer-only')
        ->assertForbidden();
});
