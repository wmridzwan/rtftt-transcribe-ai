<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <div class="flex items-center gap-2">
                    <div class="flex size-8 items-center justify-center rounded-lg bg-indigo-600">
                        <span class="text-sm font-bold text-white">RT</span>
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-zinc-900 dark:text-white">RTFTT</div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">Transcribe AI</div>
                    </div>
                </div>
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Overview')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Transcriptions')" class="grid">
                    <flux:sidebar.item icon="document-text" :href="route('transcriptions.index')" :current="request()->routeIs('transcriptions.*') && !request()->routeIs('transcriptions.create')" wire:navigate>
                        {{ __('All Transcriptions') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="arrow-up-tray" :href="route('transcriptions.create')" :current="request()->routeIs('transcriptions.create')" wire:navigate>
                        {{ __('Upload Recording') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Files')" class="grid">
                    <flux:sidebar.item icon="folder" :href="route('media.index')" :current="request()->routeIs('media.*')" wire:navigate>
                        {{ __('Media Library') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                @if (auth()->user()->isAdmin())
                    <flux:sidebar.group :heading="__('System')" class="grid">
                        <flux:sidebar.item icon="cog-6-tooth" :href="route('jobs.index')" :current="request()->routeIs('jobs.*')" wire:navigate>
                            {{ __('Processing Jobs') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Account')" class="grid">
                    <flux:sidebar.item icon="cog" :href="route('profile.edit')" :current="Str::startsWith(request()->url(), url('settings'))" wire:navigate>
                        {{ __('Settings') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <div class="border-t border-zinc-200 px-3 py-3 dark:border-zinc-700">
                <div class="flex items-center gap-3">
                    <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" size="sm" />
                    <div class="flex-1 overflow-hidden">
                        <div class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ auth()->user()->name }}</div>
                        <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ auth()->user()->role->value }}</div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <flux:button variant="subtle" size="sm" as="button" type="submit" icon="arrow-right-start-on-rectangle" />
                    </form>
                </div>
            </div>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @if (session('success'))
            <div class="fixed right-6 top-6 z-50 rounded-lg bg-emerald-600 px-4 py-3 text-sm font-medium text-white shadow-lg" role="status">
                {{ session('success') }}
            </div>
        @endif

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
