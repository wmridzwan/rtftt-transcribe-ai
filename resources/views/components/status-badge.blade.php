@props([
    'value',
    'label' => null,
])

@php
    $color = match($value) {
        'completed', 'ready' => 'green',
        'processing', 'transcribing', 'running' => 'blue',
        'queued', 'draft', 'uploaded' => 'yellow',
        'failed' => 'red',
        'cancelled', 'deleted' => 'gray',
        default => 'gray',
    };
@endphp

<flux:badge :color="$color" size="sm">{{ $label ?? ucfirst($value) }}</flux:badge>
