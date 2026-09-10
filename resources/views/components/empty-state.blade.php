@props([
    'title' => 'Nothing here yet',
    'description' => null,
    'icon' => 'document-text',
    'actionText' => null,
    'actionHref' => null,
])

<div class="flex flex-col items-center justify-center py-12 text-center">
    <div class="flex size-16 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
        @if ($icon === 'folder')
            <flux:icon.folder class="size-8 text-zinc-400" />
        @elseif ($icon === 'cog-6-tooth')
            <flux:icon.cog-6-tooth class="size-8 text-zinc-400" />
        @else
            <flux:icon.document-text class="size-8 text-zinc-400" />
        @endif
    </div>
    <flux:heading size="lg" class="mt-4">{{ $title }}</flux:heading>
    @if ($description)
        <flux:text class="mt-2 max-w-sm">{{ $description }}</flux:text>
    @endif
    @if ($actionText && $actionHref)
        <flux:button :href="$actionHref" class="mt-4" wire:navigate>{{ $actionText }}</flux:button>
    @endif
</div>
