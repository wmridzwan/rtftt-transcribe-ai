@props([
    'label',
    'value',
])

<div class="flex items-center justify-between py-2">
    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $label }}</flux:text>
    <flux:text class="text-sm font-medium text-zinc-900 dark:text-white">{{ $value }}</flux:text>
</div>
