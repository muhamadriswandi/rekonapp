<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Instansi;
use Illuminate\Auth\Access\HandlesAuthorization;

class InstansiPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Instansi');
    }

    public function view(AuthUser $authUser, Instansi $instansi): bool
    {
        return $authUser->can('View:Instansi');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Instansi');
    }

    public function update(AuthUser $authUser, Instansi $instansi): bool
    {
        return $authUser->can('Update:Instansi');
    }

    public function delete(AuthUser $authUser, Instansi $instansi): bool
    {
        return $authUser->can('Delete:Instansi');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Instansi');
    }

    public function restore(AuthUser $authUser, Instansi $instansi): bool
    {
        return $authUser->can('Restore:Instansi');
    }

    public function forceDelete(AuthUser $authUser, Instansi $instansi): bool
    {
        return $authUser->can('ForceDelete:Instansi');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Instansi');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Instansi');
    }

    public function replicate(AuthUser $authUser, Instansi $instansi): bool
    {
        return $authUser->can('Replicate:Instansi');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Instansi');
    }

}