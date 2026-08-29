<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContentPurpose;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContentUploadRequest extends FormRequest
{
    /**
     * Sin {id} en la ruta: el destino siempre es $request->user()->provider,
     * así que no hay forma de tocar el contenido de otra profesional.
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
            'purpose' => ['required', Rule::enum(ContentPurpose::class)],
            'photos' => ['required', 'array', 'min:1', 'max:10'],
            // La validación de imagen mira la cabecera nada más; la defensa
            // de verdad es que StoreProviderImage reencoda todo (ver ahí).
            'photos.*' => ['required', 'image', 'max:8192'],
            // Solo tiene sentido en una referencia: es lo que ella dijo sobre
            // por qué le gusta.
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
