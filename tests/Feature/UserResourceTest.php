<?php

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed roles
    $this->operatorRole = Role::create(['name' => 'Operator']);
    $this->supervisorRole = Role::create(['name' => 'Supervisor']);

    // Seed permissions
    $permissions = [
        'ViewAny:User',
        'View:User',
        'Create:User',
        'Update:User',
        'Delete:User',
        'DeleteAny:User',
    ];
    foreach ($permissions as $permission) {
        \Spatie\Permission\Models\Permission::create(['name' => $permission]);
    }
    $this->operatorRole->givePermissionTo($permissions);
    $this->supervisorRole->givePermissionTo(['ViewAny:User', 'View:User']);

    // Create Operator user
    $this->operator = User::create([
        'name' => 'Operator User',
        'email' => 'operator@test.com',
        'password' => bcrypt('password')
    ]);
    $this->operator->assignRole($this->operatorRole);

    // Create Supervisor user
    $this->supervisor = User::create([
        'name' => 'Supervisor User',
        'email' => 'supervisor@test.com',
        'password' => bcrypt('password')
    ]);
    $this->supervisor->assignRole($this->supervisorRole);

    // Create a plain user without roles
    $this->plainUser = User::create([
        'name' => 'Plain User',
        'email' => 'plain@test.com',
        'password' => bcrypt('password')
    ]);
});

test('operator has full CRUD access to user policy actions', function () {
    $targetUser = User::create([
        'name' => 'Target User',
        'email' => 'target@test.com',
        'password' => bcrypt('password')
    ]);

    expect($this->operator->can('viewAny', User::class))->toBeTrue();
    expect($this->operator->can('view', $targetUser))->toBeTrue();
    expect($this->operator->can('create', User::class))->toBeTrue();
    expect($this->operator->can('update', $targetUser))->toBeTrue();
    expect($this->operator->can('delete', $targetUser))->toBeTrue();
    expect($this->operator->can('deleteAny', User::class))->toBeTrue();
});

test('supervisor has read-only access to user policy actions', function () {
    $targetUser = User::create([
        'name' => 'Target User',
        'email' => 'target@test.com',
        'password' => bcrypt('password')
    ]);

    expect($this->supervisor->can('viewAny', User::class))->toBeTrue();
    expect($this->supervisor->can('view', $targetUser))->toBeTrue();

    // Restricted actions
    expect($this->supervisor->can('create', User::class))->toBeFalse();
    expect($this->supervisor->can('update', $targetUser))->toBeFalse();
    expect($this->supervisor->can('delete', $targetUser))->toBeFalse();
    expect($this->supervisor->can('deleteAny', User::class))->toBeFalse();
});

test('plain user without roles has no access to user policy actions', function () {
    $targetUser = User::create([
        'name' => 'Target User',
        'email' => 'target@test.com',
        'password' => bcrypt('password')
    ]);

    expect($this->plainUser->can('viewAny', User::class))->toBeFalse();
    expect($this->plainUser->can('view', $targetUser))->toBeFalse();
    expect($this->plainUser->can('create', User::class))->toBeFalse();
    expect($this->plainUser->can('update', $targetUser))->toBeFalse();
    expect($this->plainUser->can('delete', $targetUser))->toBeFalse();
    expect($this->plainUser->can('deleteAny', User::class))->toBeFalse();
});
