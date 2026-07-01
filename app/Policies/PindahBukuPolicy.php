<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PindahBuku;
use Illuminate\Auth\Access\HandlesAuthorization;

class PindahBukuPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PindahBuku');
    }

    public function view(AuthUser $authUser, PindahBuku $pindahBuku): bool
    {
        return $authUser->can('View:PindahBuku');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PindahBuku');
    }

    public function update(AuthUser $authUser, PindahBuku $pindahBuku): bool
    {
        return $authUser->can('Update:PindahBuku');
    }

    public function delete(AuthUser $authUser, PindahBuku $pindahBuku): bool
    {
        return $authUser->can('Delete:PindahBuku');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PindahBuku');
    }

    public function restore(AuthUser $authUser, PindahBuku $pindahBuku): bool
    {
        return $authUser->can('Restore:PindahBuku');
    }

    public function forceDelete(AuthUser $authUser, PindahBuku $pindahBuku): bool
    {
        return $authUser->can('ForceDelete:PindahBuku');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PindahBuku');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PindahBuku');
    }

    public function replicate(AuthUser $authUser, PindahBuku $pindahBuku): bool
    {
        return $authUser->can('Replicate:PindahBuku');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PindahBuku');
    }

    public function tutupBuku(AuthUser $authUser, PindahBuku $pindahBuku): bool
    {
        return $authUser->can('Update:PindahBuku') || $authUser->hasRole(['Supervisor', 'super_admin', 'Super Admin']);
    }
}
