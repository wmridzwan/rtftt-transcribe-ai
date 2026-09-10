<x-layouts::app :title="__('Settings')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <x-page-header title="Settings" description="Manage your account and application settings" />

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-6">
                <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
                    <flux:heading size="sm" class="mb-4">Profile</flux:heading>
                    <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        <x-metadata-row label="Name" :value="auth()->user()->name" />
                        <x-metadata-row label="Email" :value="auth()->user()->email" />
                        <x-metadata-row label="Role" :value="auth()->user()->role->value" />
                    </div>
                    <div class="mt-4">
                        <flux:button href="{{ route('profile.edit') }}" variant="subtle" size="sm" wire:navigate>Edit Profile</flux:button>
                    </div>
                </div>

                <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
                    <flux:heading size="sm" class="mb-4">Transcription Settings</flux:heading>
                    <div class="space-y-3">
                        <x-metadata-row label="Default Model" value="faster-whisper-medium" />
                        <x-metadata-row label="Supported Languages" value="English, Bahasa Melayu, Chinese" />
                        <x-metadata-row label="Auto Language Detection" value="Enabled" />
                    </div>
                    <p class="mt-4 text-xs text-zinc-500 dark:text-zinc-400">Transcription settings are for reference only in Phase 1. Configuration will be available in a future phase.</p>
                </div>
            </div>

            <div class="lg:col-span-1">
                <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
                    <flux:heading size="sm" class="mb-4">Storage</flux:heading>
                    <div class="space-y-3">
                        <x-metadata-row label="Storage Used" :value="number_format($storageUsed / 1048576, 2) . ' MB'" />
                        <x-metadata-row label="Total Files" :value="$totalFiles" />
                        <x-metadata-row label="Total Transcriptions" :value="$totalTranscriptions" />
                    </div>
                    <p class="mt-4 text-xs text-zinc-500 dark:text-zinc-400">Storage information is calculated from your media files.</p>
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>
