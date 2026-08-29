<?php

namespace App\Http\Requests\Admin;

use App\Actions\Content\StoreReferenceVideo;
use App\Enums\ContentPurpose;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
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
            'photos' => ['required', 'array', 'min:1', 'max:10', $this->oneVideoAtATime()],
            'photos.*' => ['required', 'file', $this->imageOrVideo()],
            // Solo tiene sentido en una referencia: es lo que ella dijo sobre
            // por qué le gusta.
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Cada archivo es una foto o un video, con su propio techo de tamaño.
     *
     * Escrito como cierre y no con Rule::when porque esa condición se evalúa
     * UNA vez para toda la regla, no por archivo — con un video en la tanda
     * seguía exigiendo 'image' y lo rechazaba con un mensaje que no explicaba
     * nada. Verificado con una prueba antes de escribir esto.
     *
     * La validación de tipo mira poco más que la cabecera; la defensa real
     * está en cómo se guarda cada uno (StoreProviderImage reencoda a WebP,
     * StoreReferenceVideo fija extensión y tipo desde una lista cerrada).
     */
    private function imageOrVideo(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! $value instanceof UploadedFile) {
                return;
            }

            $mime = (string) $value->getMimeType();

            if (str_starts_with($mime, 'video/')) {
                if (! in_array($mime, StoreReferenceVideo::allowedMimes(), true)) {
                    $fail(__('admin.contentVideoType'));

                    return;
                }

                // 50 MB: por debajo del corte de 100 MB de Cloudflare, con
                // margen para la sobrecarga del envío.
                if ($value->getSize() > 50 * 1024 * 1024) {
                    $fail(__('admin.contentVideoTooBig'));
                }

                return;
            }

            if (@getimagesize($value->getRealPath()) === false) {
                $fail(__('validation.image', ['attribute' => $attribute]));

                return;
            }

            if ($value->getSize() > 8 * 1024 * 1024) {
                $fail(__('admin.contentPhotoTooBig'));
            }
        };
    }

    /**
     * Un video por vez, y nunca mezclado con fotos ni mandado a editar.
     *
     * No es una limitación de gusto: el collage necesita imágenes, así que un
     * video en la tanda de 'edit' no tendría con qué armarse. Y de a uno
     * porque el envío entero pasa por Cloudflare, que corta en 100 MB — dos
     * videos de teléfono ya no caben.
     */
    private function oneVideoAtATime(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_array($value)) {
                return;
            }

            $videos = array_filter(
                $value,
                fn (mixed $file): bool => $file instanceof UploadedFile
                    && str_starts_with((string) $file->getMimeType(), 'video/'),
            );

            if ($videos === []) {
                return;
            }

            if ($this->input('purpose') !== ContentPurpose::Reference->value) {
                $fail(__('admin.contentVideoOnlyReference'));

                return;
            }

            if (count($videos) > 1 || count($value) > 1) {
                $fail(__('admin.contentOneVideoAtATime'));
            }
        };
    }
}
