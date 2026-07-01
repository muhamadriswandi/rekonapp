<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\RelasiBank;
use Illuminate\Auth\Access\HandlesAuthorization;

class RelasiBankPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RelasiBank');
    }

    public function view(AuthUser $authUser, RelasiBank $relasiBank): bool
    {
        return $authUser->can('View:RelasiBank');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RelasiBank');
    }

    public function update(AuthUser $authUser, RelasiBank $relasiBank): bool
    {
        return $authUser->can('Update:RelasiBank');
    }

    public function delete(AuthUser $authUser, RelasiBank $relasiBank): bool
    {
        return $authUser->can('Delete:RelasiBank');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:RelasiBank');
    }

    public function restore(AuthUser $authUser, RelasiBank $relasiBank): bool
    {
        return $authUser->can('Restore:RelasiBank');
    }

    public function forceDelete(AuthUser $authUser, RelasiBank $relasiBank): bool
    {
        return $authUser->can('ForceDelete:RelasiBank');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RelasiBank');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RelasiBank');
    }

    public function replicate(AuthUser $authUser, RelasiBank $relasiBank): bool
    {
        return $authUser->can('Replicate:RelasiBank');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RelasiBank');
    }

}