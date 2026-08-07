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
                // 20 MB matches the server's own upload_max_filesize/post_max_size
                // (php -i, verified) — a phone photo routinely lands well past the
                // old 8 MB cap, and anything above this ceiling never reaches
                // Laravel's validator anyway, PHP rejects it first.
                File::image()->max(20 * 1024),
                // A tiny compressed file can still decode into a huge
                // bitmap (decompression bomb) — cap dimensions separately
                // from the byte-size limit.
                Rule::dimensions()->maxWidth(6000)->maxHeight(6000),
            ],
        ];
    }
}
