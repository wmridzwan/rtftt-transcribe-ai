<?php

use App\Models\MediaFile;
use App\Models\ProcessingJob;
use App\Models\Transcription;
use App\Models\User;

test('dashboard renders', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('Dashboard');
});

test('transcriptions index renders', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('transcriptions.index'));
    $response->assertOk();
    $response->assertSee('Transcriptions');
});

test('transcription create renders', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('transcriptions.create'));
    $response->assertOk();
    $response->assertSee('Upload Recording');
});

test('media index renders', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('media.index'));
    $response->assertOk();
    $response->assertSee('Media Library');
});

test('application navigation exposes folders and canonical settings', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertSee(route('folders.index'), false)
        ->assertSee(route('settings.index'), false);
});

test('empty folders state provides a create folder action', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('folders.index'))
        ->assertOk()
        ->assertSee('Create Folder')
        ->assertSee('wire:click="$set(\'showCreateModal\', true)"', false);
});

test('settings renders', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('settings.index'));
    $response->assertOk();
    $response->assertSee('Settings');
});

test('admin jobs index renders', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $response = $this->get(route('jobs.index'));
    $response->assertOk();
    $response->assertSee('Processing Jobs');
});

test('transcription detail renders without literal @props', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $transcription = Transcription::factory()->create(['user_id' => $user->id]);
    $response = $this->get(route('transcriptions.show', $transcription));
    $response->assertOk();
    $response->assertDontSee('@props');
});

test('media detail renders without literal @props', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $mediaFile = MediaFile::factory()->create(['user_id' => $user->id]);
    $response = $this->get(route('media.show', $mediaFile));
    $response->assertOk();
    $response->assertDontSee('@props');
});

test('processing job detail renders without literal @props', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $job = ProcessingJob::factory()->create();
    $response = $this->get(route('jobs.show', $job));
    $response->assertOk();
    $response->assertDontSee('@props');
});

test('settings profile renders', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('profile.edit'));
    $response->assertOk();
});

test('settings security requires password confirmation', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('security.edit'));
    $response->assertRedirect();
});

test('settings appearance renders', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('appearance.edit'));
    $response->assertOk();
});

test('regular user cannot access processing jobs', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('jobs.index'));
    $response->assertForbidden();
});

test('normal users see processing summaries without admin job links', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $transcription = Transcription::factory()->create(['user_id' => $user->id]);
    $job = ProcessingJob::factory()->create(['transcription_id' => $transcription->id]);

    $this->get(route('transcriptions.show', $transcription))
        ->assertOk()
        ->assertSee('Processing')
        ->assertDontSee(route('jobs.show', $job), false);
});

test('admins retain processing job links', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $transcription = Transcription::factory()->create(['user_id' => $admin->id]);
    $job = ProcessingJob::factory()->create(['transcription_id' => $transcription->id]);

    $this->get(route('transcriptions.show', $transcription))
        ->assertSee(route('jobs.show', $job), false);
});

test('upload recording page has correct button text', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('transcriptions.create'));
    $response->assertOk();
    $response->assertSee('Create Transcription');
    $response->assertDontSee('Create Demo Transcription');
});

test('upload recording page shows demo mode notice', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('transcriptions.create'));
    $response->assertOk();
    $response->assertSee('Demo mode');
});
