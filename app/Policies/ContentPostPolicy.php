<?php

namespace App\Policies;

use App\Models\ContentPost;
use App\Models\User;

class ContentPostPolicy
{
    /**
     * La única ruta del Taller de Contenido que lleva un {id}: subir y
     * generar apuntan siempre a $user->provider por construcción. Ésta es
     * entonces la única puerta por donde alguien podría intentar calificar
     * el post de otra profesional.
     */
    public function update(User $user, ContentPost $post): bool
    {
        $user->loadMissing('provider');

        return $post->provider_id === $user->provider?->id;
    }
}
