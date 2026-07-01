<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\KanalPembayaran;
use Illuminate\Auth\Access\HandlesAuthorization;

class KanalPembayaranPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:KanalPembayaran');
    }

    public function view(AuthUser $authUser, KanalPembayaran $kanalPembayaran): bool
    {
        return $authUser->can('View:KanalPembayaran');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:KanalPembayaran');
    }

    public function update(AuthUser $authUser, KanalPembayaran $kanalPembayaran): bool
    {
        return $authUser->can('Update:KanalPembayaran');
    }

    public function delete(AuthUser $authUser, KanalPembayaran $kanalPembayaran): bool
    {
        return $authUser->can('Delete:KanalPembayaran');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:KanalPembayaran');
    }

    public function restore(AuthUser $authUser, KanalPembayaran $kanalPembayaran): bool
    {
        return $authUser->can('Restore:KanalPembayaran');
    }

    public function forceDelete(AuthUser $authUser, KanalPembayaran $kanalPembayaran): bool
    {
        return $authUser->can('ForceDelete:KanalPembayaran');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:KanalPembayaran');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:KanalPembayaran');
    }

    public function replicate(AuthUser $authUser, KanalPembayaran $kanalPembayaran): bool
    {
        return $authUser->can('Replicate:KanalPembayaran');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:KanalPembayaran');
    }

}