<?php

namespace App\Policies;

use App\Models\AppUser;
use App\Models\Submission;

/** Access rules for entregas (Submission): docente = owner only, instructor = admin. */
class EntregaPolicy
{
    public function viewAny(AppUser $user): bool
    {
        return true; // the controller scopes the list to the owner for non-admins
    }

    public function view(AppUser $user, Submission $entrega): bool
    {
        return $user->esAdmin() || $entrega->user_id === $user->id;
    }

    /** A docente can only submit as themselves; an admin can submit on behalf of anyone. */
    public function create(AppUser $user, ?string $docenteId = null): bool
    {
        return $user->esAdmin() || $docenteId === null || $docenteId === $user->id;
    }

    public function delete(AppUser $user, ?Submission $entrega = null): bool
    {
        return $user->esAdmin();
    }
}
