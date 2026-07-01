<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:User');
    }

    public function view(User $user, User $record): bool
    {
        return $user->can('View:User');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:User');
    }

    public function update(User $user, User $record): bool
    {
        return $user->can('Update:User');
    }

    public function delete(User $user, User $record): bool
    {
        return $user->can('Delete:User');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:User');
    }
}