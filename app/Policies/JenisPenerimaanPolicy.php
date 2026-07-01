<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\JenisPenerimaan;
use Illuminate\Auth\Access\HandlesAuthorization;

class JenisPenerimaanPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:JenisPenerimaan');
    }

    public function view(AuthUser $authUser, JenisPenerimaan $jenisPenerimaan): bool
    {
        return $authUser->can('View:JenisPenerimaan');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:JenisPenerimaan');
    }

    public function update(AuthUser $authUser, JenisPenerimaan $jenisPenerimaan): bool
    {
        return $authUser->can('Update:JenisPenerimaan');
    }

    public function delete(AuthUser $authUser, JenisPenerimaan $jenisPenerimaan): bool
    {
        return $authUser->can('Delete:JenisPenerimaan');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:JenisPenerimaan');
    }

    public function restore(AuthUser $authUser, JenisPenerimaan $jenisPenerimaan): bool
    {
        return $authUser->can('Restore:JenisPenerimaan');
    }

    public function forceDelete(AuthUser $authUser, JenisPenerimaan $jenisPenerimaan): bool
    {
        return $authUser->can('ForceDelete:JenisPenerimaan');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:JenisPenerimaan');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:JenisPenerimaan');
    }

    public function replicate(AuthUser $authUser, JenisPenerimaan $jenisPenerimaan): bool
    {
        return $authUser->can('Replicate:JenisPenerimaan');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:JenisPenerimaan');
    }

}