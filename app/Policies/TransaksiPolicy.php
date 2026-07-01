<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Transaksi;
use Illuminate\Auth\Access\HandlesAuthorization;

class TransaksiPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $user): bool
    {
        \Illuminate\Support\Facades\Log::info('DEBUG viewAny: Class = ' . get_class($user) . ' | email = ' . $user->email . ' | hasRole(Operator) = ' . ($user->hasRole('Operator') ? 'YES' : 'NO') . ' | raw roles = ' . json_encode($user->roles->pluck('name')));
        return $user->hasRole(['Operator', 'Supervisor']);
    }

    public function view(AuthUser $authUser, Transaksi $transaksi): bool
    {
        return $authUser->can('View:Transaksi');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Transaksi');
    }

    public function update(AuthUser $authUser, Transaksi $transaksi): bool
    {
        return $authUser->can('Update:Transaksi');
    }

    public function delete(AuthUser $authUser, Transaksi $transaksi): bool
    {
        return $authUser->can('Delete:Transaksi');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Transaksi');
    }

    public function restore(AuthUser $authUser, Transaksi $transaksi): bool
    {
        return $authUser->can('Restore:Transaksi');
    }

    public function forceDelete(AuthUser $authUser, Transaksi $transaksi): bool
    {
        return $authUser->can('ForceDelete:Transaksi');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Transaksi');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Transaksi');
    }

    public function replicate(AuthUser $authUser, Transaksi $transaksi): bool
    {
        return $authUser->can('Replicate:Transaksi');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Transaksi');
    }

    public function uploadCsv(AuthUser $authUser): bool
    {
        return $authUser->hasRole('Operator') || $authUser->can('Create:Transaksi');
    }

    public function rincian(AuthUser $authUser, Transaksi $transaksi): bool
    {
        return $authUser->hasRole('Operator') || $authUser->can('Update:Transaksi');
    }

    public function validate(AuthUser $authUser): bool
    {
        return $authUser->hasRole('Operator') || $authUser->can('Update:Transaksi');
    }
}