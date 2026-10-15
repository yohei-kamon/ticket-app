<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden mr-2" icon="bars-2" inset="left" />

            <x-app-logo href="{{ route('home') }}" wire:navigate />

            <flux:navbar class="-mb-px max-lg:hidden">
                <flux:navbar.item icon="calendar-days" :href="route('home')" :current="request()->routeIs('home')" wire:navigate>
                    イベント一覧
                </flux:navbar.item>

                @auth
                    <flux:navbar.item icon="ticket" :href="route('my.orders.index')" :current="request()->routeIs('my.orders.*')" wire:navigate>
                        マイチケット
                    </flux:navbar.item>

                    @if (auth()->user()->isOrganizer())
                        <flux:navbar.item icon="calendar" :href="route('organizer.events.index')" :current="request()->routeIs('organizer.*')" wire:navigate>
                            主催者メニュー
                        </flux:navbar.item>
                    @endif

                    @if (auth()->user()->isAdmin())
                        <flux:navbar.item icon="shield-check" href="/admin/events" :current="request()->is('admin/events*')" wire:navigate>
                            イベント管理
                        </flux:navbar.item>
                        <flux:navbar.item icon="users" href="/admin/users" :current="request()->is('admin/users*')" wire:navigate>
                            ユーザー管理
                        </flux:navbar.item>
                    @endif

                    <flux:navbar.item icon="cog" :href="route('profile.edit')" :current="request()->routeIs('profile.edit', 'security.edit', 'appearance.edit')" wire:navigate>
                        設定
                    </flux:navbar.item>
                @endauth
            </flux:navbar>

            <flux:spacer />

            @auth
                <x-desktop-user-menu />
            @else
                <flux:navbar class="me-1.5 space-x-0.5 rtl:space-x-reverse py-0!">
                    <flux:navbar.item :href="route('login')" wire:navigate>ログイン</flux:navbar.item>
                    <flux:navbar.item :href="route('register')" wire:navigate>会員登録</flux:navbar.item>
                </flux:navbar>
            @endauth
        </flux:header>

        <!-- Mobile Menu -->
        <flux:sidebar collapsible="mobile" sticky class="lg:hidden border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('home') }}" wire:navigate />
                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.item icon="calendar-days" :href="route('home')" :current="request()->routeIs('home')" wire:navigate>
                    イベント一覧
                </flux:sidebar.item>

                @auth
                    <flux:sidebar.item icon="ticket" :href="route('my.orders.index')" :current="request()->routeIs('my.orders.*')" wire:navigate>
                        マイチケット
                    </flux:sidebar.item>

                    @if (auth()->user()->isOrganizer())
                        <flux:sidebar.item icon="calendar" :href="route('organizer.events.index')" :current="request()->routeIs('organizer.*')" wire:navigate>
                            主催者メニュー
                        </flux:sidebar.item>
                    @endif

                    @if (auth()->user()->isAdmin())
                        <flux:sidebar.item icon="shield-check" href="/admin/events" :current="request()->is('admin/events*')" wire:navigate>
                            イベント管理
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="users" href="/admin/users" :current="request()->is('admin/users*')" wire:navigate>
                            ユーザー管理
                        </flux:sidebar.item>
                    @endif

                    <flux:sidebar.item icon="cog" :href="route('profile.edit')" :current="request()->routeIs('profile.edit', 'security.edit', 'appearance.edit')" wire:navigate>
                        設定
                    </flux:sidebar.item>
                @else
                    <flux:sidebar.item icon="arrow-right-end-on-rectangle" :href="route('login')" wire:navigate>
                        ログイン
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="user-plus" :href="route('register')" wire:navigate>
                        会員登録
                    </flux:sidebar.item>
                @endauth
            </flux:sidebar.nav>
        </flux:sidebar>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
