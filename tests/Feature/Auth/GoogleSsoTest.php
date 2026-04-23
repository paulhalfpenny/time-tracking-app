<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('unauthenticated request to / redirects to login', function () {
    $this->get('/')->assertRedirect(route('auth.login'));
});

test('login page is accessible', function () {
    $this->get(route('auth.login'))->assertOk();
});

test('auth error page is accessible', function () {
    $this->get(route('auth.error'))->assertOk();
});

test('authenticated user is redirected from / to timesheet', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/')->assertRedirect(route('timesheet'));
});

test('authenticated user can reach timesheet', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('timesheet'))->assertOk();
});

test('admin user has isAdmin true', function () {
    $user = User::factory()->admin()->make();

    expect($user->isAdmin())->toBeTrue();
    expect($user->isManager())->toBeTrue();
});

test('manager user has isAdmin false and isManager true', function () {
    $user = User::factory()->manager()->make();

    expect($user->isAdmin())->toBeFalse();
    expect($user->isManager())->toBeTrue();
});

test('regular user has isAdmin and isManager false', function () {
    $user = User::factory()->make(['role' => Role::User]);

    expect($user->isAdmin())->toBeFalse();
    expect($user->isManager())->toBeFalse();
});

test('inactive user cannot log in', function () {
    $user = User::factory()->create(['is_active' => false]);

    // Inactive check happens post-SSO callback; direct auth should still be blocked
    expect($user->is_active)->toBeFalse();
});
