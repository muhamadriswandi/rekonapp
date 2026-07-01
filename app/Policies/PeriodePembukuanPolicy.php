<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PeriodePembukuan;
use Illuminate\Auth\Access\HandlesAuthorization;

class PeriodePembukuanPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PeriodePembukuan');
    }

    public function view(AuthUser $authUser, PeriodePembukuan $periodePembukuan): bool
    {
        return $authUser->can('View:PeriodePembukuan');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PeriodePembukuan');
    }

    public function update(AuthUser $authUser, PeriodePembukuan $periodePembukuan): bool
    {
        return $authUser->can('Update:PeriodePembukuan');
    }

    public function delete(AuthUser $authUser, PeriodePembukuan $periodePembukuan): bool
    {
        return $authUser->can('Delete:PeriodePembukuan');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PeriodePembukuan');
    }

    public function restore(AuthUser $authUser, PeriodePembukuan $periodePembukuan): bool
    {
        return $authUser->can('Restore:PeriodePembukuan');
    }

    public function forceDelete(AuthUser $authUser, PeriodePembukuan $periodePembukuan): bool
    {
        return $authUser->can('ForceDelete:PeriodePembukuan');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PeriodePembukuan');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PeriodePembukuan');
    }

    public function replicate(AuthUser $authUser, PeriodePembukuan $periodePembukuan): bool
    {
        return $authUser->can('Replicate:PeriodePembukuan');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PeriodePembukuan');
    }

    public function tutupBuku(AuthUser $authUser, PeriodePembukuan $periodePembukuan): bool
    {
        return $authUser->can('Update:PeriodePembukuan') || $authUser->hasRole(['Supervisor', 'super_admin', 'Super Admin']);
    }
}