<script setup>
import { computed, ref, onBeforeUnmount } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Sparkles, Pin, ChevronRight, Check, Download, Clapperboard, Trash2 } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import BottomSheet from '../../Components/ui/BottomSheet.vue';
import Textarea from '../../Components/ui/Textarea.vue';
import UploadOverlay from '../../Components/ui/UploadOverlay.vue';
import { useHaptics } from '../../composables/useHaptics';

/**
 * Taller de Contenido: ella sube las fotos del día y la app le devuelve el
 * post armado, listo para publicar a mano.
 *
 * Lo primero que se pregunta al subir es para qué son las fotos — armar un
 * post o enseñar estilo. Adivinarlo fallaba en los dos sentidos: armaba
 * posts que no pidió, o archivaba lo que sí quería publicar.
 */
const props = defineProps({
    providerName: { type: String, required: true },
    avatarPhoto: { type: String, default: '' },
    waiting: { type: Array, required: true }, // [{ id, url }] subidas sin usar
    references: { type: Array, required: true }, // [{ id, url, kind, note }]
    posts: { type: Array, required: true }, // [{ id, url, caption, hashtags, rating }]
    layouts: { type: Array, required: true }, // [{ value, counts, uses, fits }]
});

const { t } = useI18n();
const haptics = useHaptics();

const fileInput = ref(null);
const chosen = ref([]); // File[]
const previews = ref([]); // objectURL[]
const purposeOpen = ref(false);
const noteOpen = ref(false);

const uploadForm = useForm({ purpose: '', note: '', photos: [] });
const generateForm = useForm({ layout: 'collage_4', uploadIds: [] });
const rateForm = useForm({ rating: '', note: '' });

const ratingPost = ref(null);
const rateOpen = ref(false);

function releasePreviews() {
    previews.value.forEach((url) => URL.revokeObjectURL(url));
    previews.value = [];
}

onBeforeUnmount(releasePreviews);

// Un video ocupa la tanda entero: el envío pasa por Cloudflare, que corta en
// 100 MB, y además un collage no se puede armar con video. Se recorta acá
// para que ella lo vea de una, en vez de que el servidor lo rechace después
// de haber esperado la subida.
const hasVideo = computed(() => chosen.value.some((file) => file.type.startsWith('video/')));

function pickFiles(event) {
    let files = Array.from(event.target.files ?? []);
    if (files.length === 0) return;

    // Las fotos ya no se recortan a diez: se suben en tandas (ver
    // makeBatches). Antes se descartaban en silencio las que sobraban, que
    // desde el teléfono se ve como que la app perdió fotos.
    const video = files.find((file) => file.type.startsWith('video/'));
    files = video ? [video] : files;

    releasePreviews();
    chosen.value = files;
    previews.value = files.map((file) => URL.createObjectURL(file));
    purposeOpen.value = true;

    // Permite volver a elegir el mismo archivo: sin esto, el input no
    // dispara change la segunda vez.
    event.target.value = '';
}

/**
 * Cuánto puede pesar UNA petición.
 *
 * El techo real no es del servidor: el sitio va detrás de Cloudflare, que
 * corta cualquier envío de más de 100 MB y lo hace ANTES de que la petición
 * llegue — no aparece ni en los registros, así que desde el teléfono parece
 * que la app se quedó pensando. Visto de verdad el 29-ago con fotos de
 * iPhone. 40 MB deja margen de sobra en una red móvil.
 */
const BATCH_BYTES = 40 * 1024 * 1024;
const BATCH_FILES = 10;

const batchTotal = ref(0);
const batchDone = ref(0);

/**
 * Parte la selección en tandas que sí pasan.
 *
 * Se parte por peso Y por cantidad: veinte fotos chicas pasan el límite de
 * archivos aunque no el de peso. Un archivo que por sí solo no cabe se manda
 * igual en su propia tanda — el servidor le dará un mensaje claro, que es
 * mejor que descartarlo en silencio acá.
 */
function makeBatches(files) {
    const batches = [];
    let current = [];
    let size = 0;

    for (const file of files) {
        const wouldOverflow = size + file.size > BATCH_BYTES || current.length >= BATCH_FILES;

        if (current.length > 0 && wouldOverflow) {
            batches.push(current);
            current = [];
            size = 0;
        }

        current.push(file);
        size += file.size;
    }

    if (current.length > 0) batches.push(current);

    return batches;
}

function send(purpose, note = '') {
    const batches = makeBatches(chosen.value);

    batchTotal.value = batches.length;
    batchDone.value = 0;

    sendBatch(batches, 0, purpose, note);
}

function sendBatch(batches, index, purpose, note) {
    if (index >= batches.length) {
        haptics.success();
        releasePreviews();
        chosen.value = [];
        purposeOpen.value = false;
        noteOpen.value = false;
        uploadForm.reset();
        batchTotal.value = 0;

        return;
    }

    uploadForm.purpose = purpose;
    // La nota describe la selección entera, así que va solo en la primera
    // tanda — repetirla en cada una es lo que ya ahogaba las referencias.
    uploadForm.note = index === 0 ? note : '';
    uploadForm.photos = batches[index];

    uploadForm.post('/admin/contenido/subir', {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            batchDone.value = index + 1;
            sendBatch(batches, index + 1, purpose, note);
        },
        onError: () => {
            haptics.error();
            batchTotal.value = 0;
        },
    });
}

function chooseEdit() {
    send('edit');
}

function chooseReference() {
    purposeOpen.value = false;
    noteOpen.value = true;
}

// Solo los modelos que cuadran con las fotos que tiene ahora. Ofrecer uno
// que no cuadra solo produce un error después de haber esperado.
const usableLayouts = computed(() => props.layouts.filter((l) => l.fits));

function build(layout) {
    if (generateForm.processing) return;

    generateForm.layout = layout.value;
    generateForm.uploadIds = props.waiting.slice(0, layout.uses).map((item) => item.id);

    generateForm.post('/admin/contenido/generar', {
        preserveScroll: true,
        onSuccess: () => haptics.success(),
        onError: () => haptics.error(),
    });
}

function openRating(post) {
    ratingPost.value = post;
    rateForm.reset();
    rateOpen.value = true;
}

function rate(value) {
    // El pulgar arriba se guarda de una; el abajo espera el motivo, que es
    // lo único que después sirve para ajustar las plantillas.
    if (value === 'down' && rateForm.note.trim() === '') {
        rateForm.rating = 'down';

        return;
    }

    rateForm.rating = value;
    rateForm.patch(`/admin/contenido/${ratingPost.value.id}/calificar`, {
        preserveScroll: true,
        onSuccess: () => {
            haptics.success();
            rateOpen.value = false;
            ratingPost.value = null;
        },
    });
}

function copyCaption(post) {
    const text = [post.caption, (post.hashtags ?? []).join(' ')].filter(Boolean).join('\n\n');
    navigator.clipboard?.writeText(text).then(() => haptics.success()).catch(() => {});
}

// Lo recién armado va entero y arriba; lo anterior, en miniaturas. Con todos
// a tamaño completo había que desplazarse muchísimo para ver lo que acababa
// de salir, que es justo lo que viene a mirar.
const latestPost = computed(() => props.posts[0] ?? null);
const olderPosts = computed(() => props.posts.slice(1));

const openedPost = ref(null);
const postOpen = ref(false);

function openPost(post) {
    openedPost.value = post;
    postOpen.value = true;
}

// Ver una referencia entera, con lo que ella escribió, y poder quitarla.
const openedReference = ref(null);
const referenceOpen = ref(false);
const removeForm = useForm({});

function openReference(reference) {
    openedReference.value = reference;
    referenceOpen.value = true;
}

function removeReference() {
    if (!openedReference.value || removeForm.processing) return;

    removeForm.delete(`/admin/contenido/referencias/${openedReference.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            haptics.warn();
            referenceOpen.value = false;
            openedReference.value = null;
        },
    });
}

// La pantalla de carga cubre las dos esperas largas: subir archivos y armar
// el collage. Sin ella la hoja se quedaba quieta y parecía trabada.
const busy = computed(() => uploadForm.processing || generateForm.processing);

const busyPercent = computed(() => uploadForm.progress?.percentage ?? null);

const busyLabel = computed(() => {
    if (generateForm.processing) return t('content.building');

    // Con varias tandas, decir "subiendo" a secas parece que se trabó al
    // volver a empezar en 0% en cada una.
    return batchTotal.value > 1
        ? t('content.uploadingBatch', { done: batchDone.value + 1, total: batchTotal.value })
        : t('content.uploading');
});
</script>

<template>
    <AdminLayout :provider-name="providerName" :avatar-src="avatarPhoto">
        <div class="flex min-h-[60dvh] flex-col gap-4 p-5 pb-8">
            <div class="pt-1">
                <h1 class="text-[26px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">
                    {{ $t('content.title') }}
                </h1>
                <p class="mt-1.5 text-sm font-medium leading-relaxed text-[var(--text-mute)]">
                    {{ $t('content.subtitle') }}
                </p>
            </div>

            <input
                ref="fileInput"
                type="file"
                accept="image/*,video/*"
                multiple
                class="hidden"
                @change="pickFiles"
            />

            <button
                type="button"
                class="flex flex-col items-center gap-1.5 rounded-2xl border-2 border-dashed border-[var(--border-strong)] bg-[var(--surface-alt)] px-4 py-7 hover:border-[var(--gold-border)] hover:bg-[var(--gold-soft)]"
                :disabled="uploadForm.processing"
                @click="fileInput?.click()"
            >
                <Sparkles :size="22" class="text-[var(--gold)]" />
                <span class="text-[15px] font-bold text-[var(--text-strong)]">
                    {{ uploadForm.processing ? $t('content.uploading') : $t('content.uploadCta') }}
                </span>
                <span class="text-[12px] font-normal text-[var(--text-mute)]">{{ $t('content.uploadHint') }}</span>
            </button>

            <p v-if="uploadForm.errors['photos.0']" class="text-[13px] font-normal text-[var(--danger)]">
                {{ uploadForm.errors['photos.0'] }}
            </p>


            <p v-if="uploadForm.errors.photos" class="text-[13px] font-normal text-[var(--danger)]">
                {{ uploadForm.errors.photos }}
            </p>

            <!-- Fotos esperando un modelo -->
            <div
                v-if="waiting.length > 0"
                class="rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface)] p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)]"
            >
                <div class="text-[15px] font-semibold text-[var(--text-strong)]">
                    {{ $t('content.waitingTitle', waiting.length) }}
                </div>
                <div class="mt-3 grid grid-cols-4 gap-1.5">
                    <img
                        v-for="item in waiting.slice(0, 8)"
                        :key="item.id"
                        :src="item.url"
                        alt=""
                        class="aspect-square w-full rounded-lg object-cover"
                    />
                </div>

                <div v-if="usableLayouts.length > 0" class="mt-4 flex flex-col gap-2">
                    <div class="text-[12px] font-semibold uppercase tracking-wide text-[var(--text-faint)]">
                        {{ $t('content.chooseLayout') }}
                    </div>

                    <button
                        v-for="layout in usableLayouts"
                        :key="layout.value"
                        type="button"
                        :disabled="generateForm.processing"
                        class="flex w-full items-center gap-3.5 rounded-2xl border border-[var(--border-strong)] bg-[var(--surface)] p-3.5 text-left hover:border-[var(--gold-border)] hover:bg-[var(--gold-soft)] disabled:cursor-not-allowed disabled:opacity-60"
                        @click="build(layout)"
                    >
                        <!-- Una miniatura del armado dibujada con cajitas: se
                             entiende de un vistazo qué va a salir, que es lo
                             que un nombre solo no dice. -->
                        <span class="grid h-11 w-11 shrink-0 gap-[2px] rounded-lg bg-[var(--surface-mute)] p-1.5"
                            :class="{
                                'grid-cols-1': layout.value === 'hero',
                                'grid-cols-2': layout.value === 'collage',
                                'grid-cols-3 items-center': layout.value === 'carousel',
                            }"
                        >
                            <template v-if="layout.value === 'hero'">
                                <span class="rounded-[3px] bg-[var(--gold)]" />
                            </template>
                            <template v-else-if="layout.value === 'collage'">
                                <span v-for="n in 4" :key="n" class="rounded-[2px] bg-[var(--gold)]" />
                            </template>
                            <template v-else>
                                <span class="h-6 rounded-[2px] bg-[var(--gold)] opacity-40" />
                                <span class="h-8 rounded-[2px] bg-[var(--gold)]" />
                                <span class="h-6 rounded-[2px] bg-[var(--gold)] opacity-40" />
                            </template>
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="block text-[15px] font-bold text-[var(--text-strong)]">
                                {{ $t(`content.layout_${layout.value}`) }}
                            </span>
                            <span class="mt-0.5 block text-[12px] font-normal text-[var(--text-mute)]">
                                {{ $t(`content.layoutHint_${layout.value}`) }} · {{ $t('content.usesPhotos', layout.uses) }}
                            </span>
                        </span>

                        <ChevronRight :size="17" class="shrink-0 text-[var(--text-faint)]" />
                    </button>
                </div>
                <p v-else class="mt-2 text-center text-[12px] font-normal text-[var(--text-faint)]">
                    {{ $t('content.needOneMore') }}
                </p>
                <p class="mt-2 text-center text-[12px] font-normal text-[var(--text-faint)]">
                    {{ $t('content.noAiOnWork') }}
                </p>
            </div>

            <!-- Referencias: se ven, se abren y se pueden quitar -->
            <div class="rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface)] p-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[var(--gold-soft)]">
                        <Pin :size="17" class="text-[var(--gold-text)]" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="text-[15px] font-semibold text-[var(--text-strong)]">
                            {{ $t('content.referencesTitle', references.length) }}
                        </div>
                        <div class="mt-0.5 text-[12px] font-normal text-[var(--text-mute)]">
                            {{ $t('content.referencesHint') }}
                        </div>
                    </div>
                </div>

                <div v-if="references.length > 0" class="mt-3.5 grid grid-cols-4 gap-1.5">
                    <button
                        v-for="ref in references"
                        :key="ref.id"
                        type="button"
                        class="relative aspect-square overflow-hidden rounded-lg bg-[var(--surface-mute)]"
                        :aria-label="$t('content.openReference')"
                        @click="openReference(ref)"
                    >
                        <video v-if="ref.kind === 'video'" :src="ref.url" muted playsinline preload="metadata" class="h-full w-full object-cover" />
                        <img v-else :src="ref.url" alt="" class="h-full w-full object-cover" />

                        <span
                            v-if="ref.kind === 'video'"
                            class="absolute inset-0 flex items-center justify-center bg-black/30"
                        >
                            <Clapperboard :size="15" class="text-white" />
                        </span>
                        <!-- La chincheta marca cuáles llevan una nota escrita:
                             son las que de verdad enseñan algo. -->
                        <span
                            v-else-if="ref.note"
                            class="absolute right-1 top-1 flex h-4 w-4 items-center justify-center rounded-full bg-[var(--gold)]"
                        >
                            <Pin :size="9" class="text-white" />
                        </span>
                    </button>
                </div>
            </div>

            <!-- Lo último armado, entero. Lo de antes queda abajo en
                 miniaturas: ocupando cada uno la pantalla completa había que
                 desplazarse muchísimo para llegar a lo que acababa de salir. -->
            <div v-if="latestPost" class="flex flex-col gap-3">
                <div class="pt-1 text-[13px] font-semibold uppercase tracking-wide text-[var(--gold-text)]">
                    {{ $t('content.latestTitle') }}
                </div>

                <div
                    v-for="post in [latestPost]"
                    :key="post.id"
                    class="overflow-hidden rounded-2xl border border-[var(--gold-border)] bg-[var(--surface)] shadow-[0_1px_2px_rgba(0,0,0,0.04)]"
                >
                    <!-- Un carrusel se desliza como en Instagram; un post de
                         una sola lámina se ve entero, sin barra que no lleva
                         a ninguna parte. -->
                    <div
                        v-if="post.slides.length > 1"
                        class="flex snap-x snap-mandatory gap-1.5 overflow-x-auto"
                    >
                        <div
                            v-for="(slide, i) in post.slides"
                            :key="i"
                            class="relative w-full shrink-0 snap-center"
                        >
                            <img :src="slide" alt="" class="aspect-square w-full object-cover" />
                            <span class="absolute right-2 top-2 rounded-full bg-black/60 px-2 py-0.5 text-[11px] font-semibold text-white">
                                {{ i + 1 }}/{{ post.slides.length }}
                            </span>
                        </div>
                    </div>
                    <img v-else :src="post.url" alt="" class="aspect-square w-full object-cover" />

                    <div class="flex flex-col gap-3 p-4">
                        <p class="text-[14px] font-normal leading-relaxed text-[var(--text-body)]">{{ post.caption }}</p>

                        <div v-if="post.hashtags.length > 0" class="flex flex-wrap gap-1.5">
                            <span
                                v-for="tag in post.hashtags"
                                :key="tag"
                                class="rounded-md bg-[var(--surface-mute)] px-2 py-0.5 text-[11px] font-semibold text-[var(--text-mute)]"
                            >{{ tag }}</span>
                        </div>

                        <div class="flex gap-2">
                            <a
                                :href="post.url"
                                target="_blank"
                                rel="noopener"
                                class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-[var(--btn-bg)] py-3 text-[14px] font-semibold text-white hover:bg-[var(--btn-hover)]"
                            >
                                <Download :size="15" />
                                {{ $t('content.download') }}
                            </a>
                            <button
                                type="button"
                                class="rounded-xl border border-[var(--border-strong)] px-3.5 text-[14px] font-semibold text-[var(--text-body)] hover:bg-[var(--surface-mute)]"
                                @click="copyCaption(post)"
                            >
                                {{ $t('content.copyText') }}
                            </button>
                        </div>

                        <button
                            v-if="!post.rating"
                            type="button"
                            class="flex items-center justify-between rounded-xl bg-[var(--surface-alt)] px-3.5 py-2.5 text-left hover:bg-[var(--surface-mute)]"
                            @click="openRating(post)"
                        >
                            <span class="text-[13px] font-medium text-[var(--text-mute)]">{{ $t('content.rateAsk') }}</span>
                            <ChevronRight :size="15" class="text-[var(--text-faint)]" />
                        </button>
                        <div v-else class="flex items-center gap-1.5 text-[12px] font-medium text-[var(--green-text)]">
                            <Check :size="14" />
                            {{ post.rating === 'up' ? $t('content.ratedUp') : $t('content.ratedDown') }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Los anteriores, en miniatura. Un toque los abre entero. -->
            <div v-if="olderPosts.length > 0" class="flex flex-col gap-2.5">
                <div class="pt-1 text-[13px] font-semibold uppercase tracking-wide text-[var(--text-faint)]">
                    {{ $t('content.olderTitle', olderPosts.length) }}
                </div>
                <div class="grid grid-cols-4 gap-1.5">
                    <button
                        v-for="post in olderPosts"
                        :key="post.id"
                        type="button"
                        class="relative aspect-square overflow-hidden rounded-lg bg-[var(--surface-mute)]"
                        @click="openPost(post)"
                    >
                        <img :src="post.url" alt="" class="h-full w-full object-cover" />
                        <span
                            v-if="post.slides.length > 1"
                            class="absolute right-1 top-1 rounded-full bg-black/60 px-1.5 text-[10px] font-semibold text-white"
                        >{{ post.slides.length }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ¿Editar o referencia? -->
        <BottomSheet v-model="purposeOpen">
            <div class="mb-5">
                <div class="text-[20px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">
                    {{ $t('content.purposeTitle', chosen.length) }}
                </div>
                <p class="mt-1 text-[13px] font-normal text-[var(--text-mute)]">{{ $t('content.purposeHint') }}</p>
            </div>

            <!-- Un video se muestra reproducible; una foto, como miniatura. -->
            <video
                v-if="hasVideo"
                :src="previews[0]"
                controls
                playsinline
                class="mb-5 max-h-[220px] w-full rounded-xl bg-black object-contain"
            />
            <div v-else class="mb-5 grid grid-cols-4 gap-1.5">
                <img v-for="(url, i) in previews.slice(0, 8)" :key="i" :src="url" alt="" class="aspect-square w-full rounded-lg object-cover" />
            </div>

            <p v-if="hasVideo" class="mb-4 flex items-start gap-2 rounded-xl bg-[var(--surface-alt)] px-3.5 py-3 text-[12px] font-normal leading-relaxed text-[var(--text-mute)]">
                <Clapperboard :size="15" class="mt-0.5 shrink-0 text-[var(--text-faint)]" />
                {{ $t('content.videoOnlyReference') }}
            </p>

            <div class="flex flex-col gap-2.5">
                <button
                    v-if="!hasVideo"
                    type="button"
                    :disabled="uploadForm.processing"
                    class="flex items-center gap-3.5 rounded-2xl border border-[var(--gold-border)] bg-[var(--gold-soft)] p-4 text-left disabled:opacity-60"
                    @click="chooseEdit"
                >
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[var(--surface)]">
                        <Sparkles :size="19" class="text-[var(--gold)]" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[15px] font-bold text-[var(--text-strong)]">{{ $t('content.purposeEdit') }}</span>
                        <span class="mt-0.5 block text-[12px] font-normal text-[var(--text-mute)]">{{ $t('content.purposeEditHint') }}</span>
                    </span>
                    <ChevronRight :size="17" class="shrink-0 text-[var(--text-faint)]" />
                </button>

                <button
                    type="button"
                    :disabled="uploadForm.processing"
                    class="flex items-center gap-3.5 rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface)] p-4 text-left disabled:opacity-60"
                    @click="chooseReference"
                >
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[var(--surface-mute)]">
                        <Pin :size="19" class="text-[var(--text-mute)]" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[15px] font-bold text-[var(--text-strong)]">{{ $t('content.purposeReference') }}</span>
                        <span class="mt-0.5 block text-[12px] font-normal text-[var(--text-mute)]">{{ $t('content.purposeReferenceHint') }}</span>
                    </span>
                    <ChevronRight :size="17" class="shrink-0 text-[var(--text-faint)]" />
                </button>
            </div>
        </BottomSheet>

        <!-- Contame qué te gusta (referencia) -->
        <BottomSheet v-model="noteOpen">
            <div class="mb-4">
                <div class="text-[20px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">
                    {{ $t('content.noteTitle') }}
                </div>
                <p class="mt-1 text-[13px] font-normal text-[var(--text-mute)]">{{ $t('content.noteHint') }}</p>
            </div>

            <Textarea v-model="uploadForm.note" :label="$t('content.noteLabel')" :rows="4" />

            <button
                type="button"
                :disabled="uploadForm.processing"
                class="mt-4 w-full rounded-xl bg-[var(--btn-bg)] py-3.5 text-[15px] font-semibold text-white hover:bg-[var(--btn-hover)] disabled:opacity-60"
                @click="send('reference', uploadForm.note)"
            >
                {{ uploadForm.processing ? $t('common.saving') : $t('content.saveReference') }}
            </button>
        </BottomSheet>

        <!-- Calificar -->
        <BottomSheet v-model="rateOpen">
            <div class="mb-4">
                <div class="text-[20px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">
                    {{ $t('content.rateTitle') }}
                </div>
                <p class="mt-1 text-[13px] font-normal text-[var(--text-mute)]">{{ $t('content.rateHint') }}</p>
            </div>

            <div class="flex gap-2.5">
                <button
                    type="button"
                    class="flex-1 rounded-xl border border-[var(--border-strong)] py-3 text-[14px] font-semibold text-[var(--text-strong)] hover:border-[var(--green-border)] hover:bg-[var(--green-soft)]"
                    @click="rate('up')"
                >
                    {{ $t('content.rateUp') }}
                </button>
                <button
                    type="button"
                    class="flex-1 rounded-xl border border-[var(--border-strong)] py-3 text-[14px] font-semibold text-[var(--text-strong)] hover:border-[var(--gold-border)] hover:bg-[var(--gold-soft)]"
                    :class="rateForm.rating === 'down' && 'border-[var(--gold-border)] bg-[var(--gold-soft)]'"
                    @click="rate('down')"
                >
                    {{ $t('content.rateDown') }}
                </button>
            </div>

            <div v-if="rateForm.rating === 'down'" class="mt-4">
                <Textarea v-model="rateForm.note" :label="$t('content.rateWhy')" :rows="3" />
                <p class="mt-1.5 text-[12px] font-normal text-[var(--text-faint)]">{{ $t('content.rateWhyHint') }}</p>
                <button
                    type="button"
                    :disabled="rateForm.note.trim() === '' || rateForm.processing"
                    class="mt-3 w-full rounded-xl bg-[var(--btn-bg)] py-3.5 text-[15px] font-semibold text-white hover:bg-[var(--btn-hover)] disabled:cursor-not-allowed disabled:opacity-60"
                    @click="rate('down')"
                >
                    {{ rateForm.processing ? $t('common.saving') : $t('content.rateSend') }}
                </button>
            </div>
        </BottomSheet>

        <!-- Un post anterior, abierto entero -->
        <BottomSheet v-model="postOpen">
            <template v-if="openedPost">
                <div v-if="openedPost.slides.length > 1" class="mb-4 flex snap-x snap-mandatory gap-1.5 overflow-x-auto">
                    <img
                        v-for="(slide, i) in openedPost.slides"
                        :key="i"
                        :src="slide"
                        alt=""
                        class="aspect-square w-full shrink-0 snap-center rounded-xl object-cover"
                    />
                </div>
                <img v-else :src="openedPost.url" alt="" class="mb-4 aspect-square w-full rounded-xl object-cover" />

                <p class="mb-3 text-[14px] font-normal leading-relaxed text-[var(--text-body)]">{{ openedPost.caption }}</p>

                <div v-if="openedPost.hashtags.length > 0" class="mb-4 flex flex-wrap gap-1.5">
                    <span
                        v-for="tag in openedPost.hashtags"
                        :key="tag"
                        class="rounded-md bg-[var(--surface-mute)] px-2 py-0.5 text-[11px] font-semibold text-[var(--text-mute)]"
                    >{{ tag }}</span>
                </div>

                <div class="flex gap-2">
                    <a
                        :href="openedPost.url"
                        target="_blank"
                        rel="noopener"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-[var(--btn-bg)] py-3 text-[14px] font-semibold text-white hover:bg-[var(--btn-hover)]"
                    >
                        <Download :size="15" />
                        {{ $t('content.download') }}
                    </a>
                    <button
                        type="button"
                        class="rounded-xl border border-[var(--border-strong)] px-3.5 text-[14px] font-semibold text-[var(--text-body)] hover:bg-[var(--surface-mute)]"
                        @click="copyCaption(openedPost)"
                    >
                        {{ $t('content.copyText') }}
                    </button>
                </div>
            </template>
        </BottomSheet>

        <!-- Una referencia abierta: se ve entera y se puede quitar -->
        <BottomSheet v-model="referenceOpen">
            <template v-if="openedReference">
                <video
                    v-if="openedReference.kind === 'video'"
                    :src="openedReference.url"
                    controls
                    playsinline
                    class="mb-4 max-h-[300px] w-full rounded-xl bg-black object-contain"
                />
                <img v-else :src="openedReference.url" alt="" class="mb-4 max-h-[300px] w-full rounded-xl object-contain" />

                <div v-if="openedReference.note" class="mb-4 rounded-xl bg-[var(--surface-alt)] p-3.5">
                    <div class="text-[12px] font-semibold uppercase tracking-wide text-[var(--text-faint)]">
                        {{ $t('content.yourWords') }}
                    </div>
                    <p class="mt-1.5 text-[14px] font-normal leading-relaxed text-[var(--text-body)]">
                        {{ openedReference.note }}
                    </p>
                </div>
                <p v-else class="mb-4 text-[13px] font-normal text-[var(--text-mute)]">
                    {{ $t('content.noNote') }}
                </p>

                <button
                    type="button"
                    :disabled="removeForm.processing"
                    class="flex w-full items-center justify-center gap-2 rounded-xl border border-[var(--danger-border)] py-3 text-[14px] font-semibold text-[var(--danger)] hover:bg-[var(--danger-hover)] disabled:opacity-60"
                    @click="removeReference"
                >
                    <Trash2 :size="15" />
                    {{ removeForm.processing ? $t('common.saving') : $t('content.removeReference') }}
                </button>
            </template>
        </BottomSheet>

        <UploadOverlay
            :show="busy"
            :percent="busyPercent"
            :label="busyLabel"
            :hint="$t('content.busyHint')"
        />
    </AdminLayout>
</template>
