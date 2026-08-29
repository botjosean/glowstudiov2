<?php

namespace App\Enums;

enum UploadKind: string
{
    case Image = 'image';

    /** Solo como referencia: un tutorial, nunca material para publicar. */
    case Video = 'video';
}
