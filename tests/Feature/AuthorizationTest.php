<?php

use App\Models\ProcessingJob;
use App\Models\User;

test('admin can access processing jobs', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $response = $this->get(route('jobs.index'));
    $response->assertOk();
});

test('normal user cannot access processing jobs', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('jobs.index'));
    $response->assertForbidden();
});

test('normal user cannot access processing job detail', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $job = ProcessingJob::factory()->create();
    $response = $this->get(route('jobs.show', $job));
    $response->assertForbidden();
});

test('admin can access processing job detail', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $job = ProcessingJob::factory()->create();
    $response = $this->get(route('jobs.show', $job));
    $response->assertOk();
});
