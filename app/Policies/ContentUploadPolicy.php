<?php

namespace App\Policies;

use App\Models\ContentUpload;
use App\Models\User;

class ContentUploadPolicy
{
    /**
     * Quitar una referencia. Es la otra ruta del Taller de Contenido que
     * lleva un {id} — subir y generar apuntan siempre a $user->provider por
     * construcción. Ver también ContentPostPolicy.
     */
    public function delete(User $user, ContentUpload $upload): bool
    {
        $user->loadMissing('provider');

        return $upload->provider_id === $user->provider?->id;
    }
}
