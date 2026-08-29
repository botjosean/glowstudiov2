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
            'photos' => ['required', 'array', 'min:1', 'max:10', $this->oneVideoAtATime(), $this->batchFitsThrough()],
            // 'bail' para que, si el archivo llegó roto, la regla 'file' corte
            // ahí y el cierre de abajo no llegue a preguntarle nada.
            'photos.*' => ['required', 'bail', 'file', $this->imageOrVideo()],
            // Solo tiene sentido en una referencia: es lo que ella dijo sobre
            // por qué le gusta.
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * La tanda entera tiene que caber en una sola petición.
     *
     * Se mide el total y no solo cada archivo porque el techo real es el
     * envío completo: post_max_size en PHP y, por encima de todo, el corte de
     * 100 MB de Cloudflare. Diez fotos de 25 MB pasan la validación una por
     * una y aun así no llegan nunca al servidor.
     *
     * Con un tope por archivo bajo esto no haría falta, pero un tope bajo
     * rechazaba fotos legítimas de teléfono. Así se permite una foto enorme
     * o muchas chicas, y solo se corta la combinación que de verdad no pasa.
     */
    private function batchFitsThrough(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_array($value)) {
                return;
            }

            $total = 0;

            foreach ($value as $file) {
                if ($file instanceof UploadedFile && $file->isValid()) {
                    $total += $file->getSize();
                }
            }

            if ($total > 85 * 1024 * 1024) {
                $fail(__('admin.contentBatchTooBig'));
            }
        };
    }

    /**
     * El tipo del archivo, o cadena vacía si no se le puede preguntar.
     *
     * Existe por un 500 real en producción (29-ago): cuando una subida se
     * corta a medio camino —conexión que se cae, archivo más grande que
     * post_max_size— PHP igual entrega el UploadedFile, pero sin ruta en
     * disco. Ahí `getMimeType()` lanza 'The "" file does not exist' y la
     * profesional ve una pantalla de error en vez de saber que se le cortó
     * la subida.
     */
    private static function mimeOf(UploadedFile $file): string
    {
        if (! $file->isValid()) {
            return '';
        }

        try {
            return (string) $file->getMimeType();
        } catch (\Throwable) {
            return '';
        }
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

            $mime = self::mimeOf($value);

            if ($mime === '') {
                $fail(__('admin.contentUploadFailed'));

                return;
            }

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

            // 25 MB: un teléfono moderno saca fotos de más de 8 MB sin
            // esfuerzo, y el tope viejo se las rechazaba. No cuesta
            // almacenamiento: StoreProviderImage las reencoda a 1200² WebP
            // (unos 100 KB) antes de guardarlas, así que el tamaño de origen
            // solo afecta cuánto tarda en subir.
            if ($value->getSize() > 25 * 1024 * 1024) {
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
                    && str_starts_with(self::mimeOf($file), 'video/'),
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
