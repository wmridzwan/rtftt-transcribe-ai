@props([
    'title',
    'description' => null,
])

<div class="mb-6">
    <flux:heading size="xl">{{ $title }}</flux:heading>
    @if ($description)
        <flux:text class="mt-1">{{ $description }}</flux:text>
    @endif
</div>
