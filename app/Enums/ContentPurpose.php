<?php

namespace App\Enums;

/**
 * Lo primero que se le pregunta al subir, y a propósito: una foto para
 * editar y una foto de referencia no se parecen en nada, y adivinarlo
 * fallaba en los dos sentidos.
 */
enum ContentPurpose: string
{
    /** Para armar un post con ella. */
    case Edit = 'edit';

    /** Para enseñarle al sistema qué estilo le gusta. No genera nada. */
    case Reference = 'reference';
}
