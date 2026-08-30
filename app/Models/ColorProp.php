<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Una foto decorativa generada por IA, cacheada por rubro y por color del
 * catálogo. Ver la migración `create_color_props_table` y
 * `App\Actions\Content\FindOrCreateColorProp`.
 */
#[Fillable(['business_category', 'color_key', 'path'])]
class ColorProp extends Model {}
