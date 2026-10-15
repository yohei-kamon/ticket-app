<?php

use App\Models\User;
use Illuminate\Testing\TestView;

function renderAppLayout(): TestView
{
    return test()->blade('<x-layouts::app>本文</x-layouts::app>');
}

test('guests can see the layout with login and register links', function () {
    renderAppLayout()
        ->assertSee('本文')
        ->assertSee('イベント一覧')
        ->assertSee('ログイン')
        ->assertSee('会員登録')
        ->assertDontSee('マイチケット')
        ->assertDontSee('設定');
});

test('customers see their own menu only', function () {
    $this->actingAs(User::factory()->create());

    renderAppLayout()
        ->assertSee('イベント一覧')
        ->assertSee('マイチケット')
        ->assertSee('設定')
        ->assertDontSee('会員登録')
        ->assertDontSee('主催者メニュー')
        ->assertDontSee('ユーザー管理');
});

test('organizers see the organizer menu', function () {
    $this->actingAs(User::factory()->organizer()->create());

    renderAppLayout()
        ->assertSee('マイチケット')
        ->assertSee('主催者メニュー')
        ->assertDontSee('ユーザー管理');
});

test('settings pages render with the header layout', function (string $routeName) {
    $this->actingAs(User::factory()->create())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route($routeName))
        ->assertOk()
        ->assertSee('マイチケット')
        ->assertSee(route('security.edit'))
        ->assertSee(route('appearance.edit'));
})->with(['profile.edit', 'security.edit', 'appearance.edit']);

test('admins see the admin menus', function () {
    $this->actingAs(User::factory()->admin()->create());

    renderAppLayout()
        ->assertSee('マイチケット')
        ->assertSee('イベント管理')
        ->assertSee('ユーザー管理')
        ->assertDontSee('主催者メニュー');
});
