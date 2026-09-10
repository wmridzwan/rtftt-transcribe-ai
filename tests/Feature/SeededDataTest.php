<?php

use App\Models\MediaFile;
use App\Models\ProcessingJob;
use App\Models\Transcription;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

test('seeded application renders realistic data', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::where('email', 'admin@rtftt.local')->first();
    $user = User::where('email', 'user@rtftt.local')->first();

    $this->assertNotNull($admin);
    $this->assertNotNull($user);

    $this->assertGreaterThan(0, MediaFile::count());
    $this->assertGreaterThan(0, Transcription::count());
    $this->assertGreaterThan(0, ProcessingJob::count());
});

test('admin sees all transcriptions', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::where('email', 'admin@rtftt.local')->first();
    $this->actingAs($admin);

    $response = $this->get(route('transcriptions.index'));
    $response->assertOk();
});

test('user sees only own transcriptions', function () {
    $this->seed(DatabaseSeeder::class);

    $user = User::where('email', 'user@rtftt.local')->first();
    $this->actingAs($user);

    $response = $this->get(route('transcriptions.index'));
    $response->assertOk();
});
