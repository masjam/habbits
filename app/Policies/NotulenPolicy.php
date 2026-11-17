<?php

namespace App\Policies;

use App\Models\Notulen;
use App\Models\User;

class NotulenPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Notulen $notulen): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Notulen $notulen): bool
    {
        if ($notulen->is_approved) {
            return false;
        }

        return $user->id === $notulen->user_id
            || $user->name === $notulen->pimpinan_rapat
            || $user->hasAnyRole(['superadmin', 'admin', 'kepala_sekolah']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Notulen $notulen): bool
    {
        return $user->id === $notulen->user_id
            || $user->hasAnyRole(['superadmin', 'admin', 'kepala_sekolah']);
    }

    /**
     * Determine whether the user can approve the model.
     */
    public function approve(User $user, Notulen $notulen): bool
    {
        if ($notulen->is_approved) {
            return false;
        }

        return $user->name === $notulen->pimpinan_rapat
            || $user->hasAnyRole(['superadmin', 'admin', 'kepala_sekolah']);
    }
}
