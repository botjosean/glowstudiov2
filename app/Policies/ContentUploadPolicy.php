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

    /**
     * Poner de nuevo en la cola una foto que ya se usó en un post. Misma
     * comprobación de dueño que borrar una referencia.
     */
    public function update(User $user, ContentUpload $upload): bool
    {
        $user->loadMissing('provider');

        return $upload->provider_id === $user->provider?->id;
    }
}
