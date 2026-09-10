@props([
    'title',
    'value',
    'icon' => null,
])

<div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
    <div class="flex items-center gap-3">
        @if ($icon)
            <div class="flex size-10 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700">
                @if ($icon === 'check-circle')
                    <flux:icon.check-circle class="size-5 text-zinc-500 dark:text-zinc-400" />
                @elseif ($icon === 'arrow-path')
                    <flux:icon.arrow-path class="size-5 text-zinc-500 dark:text-zinc-400" />
                @elseif ($icon === 'x-circle')
                    <flux:icon.x-circle class="size-5 text-zinc-500 dark:text-zinc-400" />
                @else
                    <flux:icon.document-text class="size-5 text-zinc-500 dark:text-zinc-400" />
                @endif
            </div>
        @endif
        <div>
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $title }}</flux:text>
            <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ $value }}</div>
        </div>
    </div>
</div>
