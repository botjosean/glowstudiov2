<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UploadProviderPhotoRequest extends FormRequest
{
    /**
     * Authorization lives in the route — this endpoint takes no {id}, the
     * target is always $request->user()->provider, so there is no
     * cross-tenant vector to guard against by construction. Shared by the
     * avatar, banner, and gallery uploads — the rules are identical for
     * all three (only the resize target, from ImageVariant, differs).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                // No SVG: File::image() excludes it by default (XSS risk).
                File::image()->max(8 * 1024),
                // A tiny compressed file can still decode into a huge
                // bitmap (decompression bomb) — cap dimensions separately
                // from the byte-size limit.
                Rule::dimensions()->maxWidth(6000)->maxHeight(6000),
            ],
        ];
    }
}
