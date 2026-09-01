<script setup>
import { computed, ref, watch, onBeforeUnmount } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { Sparkles, Pin, ChevronRight, Check, Download, Clapperboard, Trash2, Wand } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import BottomSheet from '../../Components/ui/BottomSheet.vue';
import Textarea from '../../Components/ui/Textarea.vue';
import OutlinedInput from '../../Components/ui/OutlinedInput.vue';
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
    waiting: { type: Array, required: true }, // [{ id, url, colorName, colorHex }] subidas sin usar
    recentEdits: { type: Array, required: true }, // [{ id, url, used }] últimas fotos "para editar"
    references: { type: Array, required: true }, // [{ id, url, kind, note }]
    // La ficha leída de sus referencias, o null si todavía no la generó.
    contentStyle: { type: Object, default: null },
    posts: { type: Array, required: true }, // [{ id, url, caption, hashtags, rating }]
    layouts: { type: Array, required: true }, // [{ value, counts, uses, fits }]
});

const { t } = useI18n();
const haptics = useHaptics();
const page = usePage();

const fileInput = ref(null);
const chosen = ref([]); // File[]
const previews = ref([]); // objectURL[]
const purposeOpen = ref(false);
const noteOpen = ref(false);

const uploadForm = useForm({ purpose: '', note: '', first: false, photos: [] });
const noteForm = useForm({ uploadIds: [], note: '' });
const generateForm = useForm({ layout: 'collage_4', uploadIds: [], templateUploadId: null });
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
    // Y solo la primera pide sugerencia: las demás son las mismas fotos
    // partidas para pasar por Cloudflare.
    uploadForm.first = index === 0;
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

// Se sube primero y la nota se pide DESPUÉS, ya con una sugerencia hecha.
// Antes se le pedía escribir antes de subir, contra un cuadro vacío, y una
// referencia sin nota enseña la mitad.
function chooseReference() {
    send('reference');
}

// Solo los modelos que cuadran con las fotos que tiene ahora. Ofrecer uno
// que no cuadra solo produce un error después de haber esperado.
const usableLayouts = computed(() => props.layouts.filter((l) => l.fits));

// Elegido el diseño, falta el "¿como cuál de tus referencias?" — ella lo
// pidió directo: en vez de que el sistema mezcle todas sus referencias en
// un estilo promedio, poder elegir UNA puntual para copiar esa.
const templateOpen = ref(false);
const pendingLayout = ref(null);
// null = usar las más viejas de la cola, automático. Un array = las que ella
// misma eligió a mano (ver colorPick, para "Fondo de color").
const pendingUploadIds = ref(null);

// "Fondo de color" necesita fotos DEL MISMO trabajo, y agarrar las más
// viejas de la cola a ciegas no lo garantiza: si se subieron dos tandas de
// colores distintos antes de armar el post, se mezclan sin que se note hasta
// ver el resultado. Pasó de verdad — una tanda con una uña rosa metida entre
// dos negras — y el post salió con "ROSA" escrito sobre fotos negras. Para
// esta plantilla ella elige a mano, viendo el color de cada una.
//
// "Combinación" usa la MISMA hoja al revés: ahí dos colores distintos no es
// un error, es el pedido — dos fotos, una por color.
const colorPickOpen = ref(false);
const colorPickSelected = ref([]);
const colorPickIsCombo = computed(() => pendingLayout.value?.value === 'combo');
// Combinación es siempre una foto por color, dos y no más. Fondo de color
// admite hasta cuatro, una por esquina.
const colorPickMax = computed(() => (colorPickIsCombo.value ? 2 : 4));

// Los nombres de color de lo que lleva elegido hasta ahora, sin repetir.
const colorPickNames = computed(() => {
    const names = new Set();

    for (const id of colorPickSelected.value) {
        const item = props.waiting.find((w) => w.id === id);

        if (item?.colorName) names.add(item.colorName);
    }

    return [...names];
});

// En "Fondo de color" más de un nombre es la señal de una tanda mezclada por
// error. En "Combinación" es exactamente lo contrario: es lo que se busca,
// así que ahí nunca es una alarma.
const colorPickWarn = computed(() => !colorPickIsCombo.value && colorPickNames.value.length > 1);

function toggleColorPick(id) {
    const i = colorPickSelected.value.indexOf(id);

    if (i !== -1) {
        colorPickSelected.value.splice(i, 1);

        return;
    }

    if (colorPickSelected.value.length >= colorPickMax.value) return;

    colorPickSelected.value.push(id);
}

function confirmColorPick() {
    if (colorPickSelected.value.length < 2) return;

    pendingUploadIds.value = [...colorPickSelected.value];
    colorPickOpen.value = false;

    if (props.references.length === 0) {
        generate(pendingLayout.value, null);

        return;
    }

    templateOpen.value = true;
}

// Cuál referencia copiar, recordada entre posts.
//
// Antes esto era una hoja que se abría en CADA generación: elegir modelo,
// esperar, elegir referencia, esperar. Dos toques y dos pantallas para algo
// que ella casi nunca cambia. Ella lo dijo así: «solo elijo alguna
// plantilla, subo las fotos sin nada y listo». Ahora es un ajuste que se
// queda puesto, y la hoja solo se abre si lo quiere cambiar.
//
// null = "Sorpréndeme", que es lo que hace el sistema si nunca eligió.
const chosenTemplate = ref(null);

const chosenTemplateLabel = computed(() => {
    if (chosenTemplate.value === null) return t('content.templateSurprise');

    const i = props.references.findIndex((r) => r.id === chosenTemplate.value);

    return i === -1 ? t('content.templateSurprise') : t('content.templateNth', { n: i + 1 });
});

// Se guarda en el teléfono para que siga puesta mañana. Si el navegador la
// bloquea o la borra, se vuelve a "Sorpréndeme" sin romper nada.
try {
    const guardada = window.localStorage?.getItem('glow.contentTemplate');

    if (guardada !== null && guardada !== 'null') chosenTemplate.value = Number(guardada);
} catch { /* sin memoria del navegador se sigue igual */ }

watch(chosenTemplate, (v) => {
    try {
        window.localStorage?.setItem('glow.contentTemplate', v === null ? 'null' : String(v));
    } catch { /* idem */ }
});

function build(layout) {
    if (generateForm.processing) return;

    pendingLayout.value = layout;
    pendingUploadIds.value = null;

    if (layout.value === 'color' || layout.value === 'combo') {
        colorPickSelected.value = [];
        colorPickOpen.value = true;

        return;
    }

    generate(layout, chosenTemplate.value);
}

// Ya no arma el post: solo deja elegida la referencia para los que vengan.
function chooseTemplate(templateId) {
    chosenTemplate.value = templateId;
    templateOpen.value = false;
}

function generate(layout, templateId) {
    generateForm.layout = layout.value;
    generateForm.uploadIds = pendingUploadIds.value ?? props.waiting.slice(0, layout.uses).map((item) => item.id);
    generateForm.templateUploadId = templateId;

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

// Cuando el servidor devuelve una sugerencia de nota, se abre la hoja ya
// escrita. Se observa el objeto flash entero y no la clave: Inertia reemplaza
// las props en bloque, y mirar solo la hoja se tragaría dos sugerencias
// iguales seguidas.
watch(() => page.props.flash, (flash) => {
    const sugerencia = flash?.noteSuggestion;

    if (sugerencia) {
        noteForm.uploadIds = sugerencia.uploadIds ?? [];
        noteForm.note = sugerencia.text ?? '';
        noteOpen.value = true;
    }

    // Y lo mismo con el color del trabajo: el sistema propone lo que ve y
    // ella lo corrige. Mirando los píxeles no se puede —las uñas son una
    // parte chica del cuadro y gana la ropa del fondo—, así que lo lee el
    // modelo, pero la palabra final es la de ella.
    const color = flash?.colorSuggestion;

    if (color) {
        colorForm.uploadIds = color.uploadIds ?? [];
        colorForm.name = color.name ?? '';
        colorForm.hex = color.hex ?? '';
        colorOpen.value = true;
    }
}, { deep: true });

const colorOpen = ref(false);
const colorForm = useForm({ uploadIds: [], name: '', hex: '' });

function saveColor() {
    if (colorForm.processing) return;

    colorForm.patch('/admin/contenido/fotos/color', {
        preserveScroll: true,
        onSuccess: () => {
            haptics.success();
            colorOpen.value = false;
            colorForm.reset();
        },
    });
}

function saveNote() {
    if (noteForm.processing) return;

    noteForm.patch('/admin/contenido/referencias/nota', {
        preserveScroll: true,
        onSuccess: () => {
            haptics.success();
            noteOpen.value = false;
            noteForm.reset();
        },
    });
}

// Leer las referencias y quedarse con su estilo. Es lo que conecta lo que
// ella guarda con cómo se ven los posts — hasta ahora solo alimentaba el texto.
const styleForm = useForm({});

function learnStyle() {
    if (styleForm.processing) return;

    styleForm.post('/admin/contenido/estilo', {
        preserveScroll: true,
        onSuccess: () => haptics.success(),
        onError: () => haptics.error(),
    });
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

// Tocar una foto la mete o la saca de la cola del próximo post. Antes esto
// era una hoja que SOLO servía para meter: las que ya estaban en la cola no
// eran tocables y ella se quedó trabada — «yo la selecciono, voy agregando,
// y después ya no la puedo deseleccionar».
const queueForm = useForm({});

function toggleQueued(item) {
    if (queueForm.processing) return;

    queueForm.patch(`/admin/contenido/fotos/${item.id}/cola`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => haptics.success(),
        onError: () => haptics.error(),
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
                                'grid-cols-1': layout.value === 'hero' || layout.value === 'combo',
                                'grid-cols-2': layout.value === 'collage' || layout.value === 'color',
                                'grid-cols-3 items-center': layout.value === 'carousel',
                            }"
                        >
                            <template v-if="layout.value === 'hero'">
                                <span class="rounded-[3px] bg-[var(--gold)]" />
                            </template>
                            <template v-else-if="layout.value === 'collage'">
                                <span v-for="n in 4" :key="n" class="rounded-[2px] bg-[var(--gold)]" />
                            </template>
                            <!-- Fondo de color: las cuatro esquinas con el
                                 medio libre, que es justo lo que la define. -->
                            <template v-else-if="layout.value === 'color'">
                                <span v-for="n in 4" :key="n" class="rounded-[2px] bg-[var(--gold)] opacity-70" />
                            </template>
                            <!-- Combinación: una foto arriba, otra abajo — la
                                 tarjeta de los dos colores va en la costura. -->
                            <template v-else-if="layout.value === 'combo'">
                                <span class="h-[17px] rounded-[2px] bg-[var(--gold)]" />
                                <span class="h-[17px] rounded-[2px] bg-[var(--gold)] opacity-70" />
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

                <!-- La referencia a copiar, como ajuste y no como paso.
                     Antes se preguntaba en cada post; ahora queda puesta y
                     solo se toca si la quiere cambiar. -->
                <button
                    v-if="references.length > 0 && usableLayouts.length > 0"
                    type="button"
                    class="mt-3 flex w-full items-center justify-between rounded-xl px-1 py-2 text-left"
                    @click="templateOpen = true"
                >
                    <span class="text-[12px] font-normal text-[var(--text-mute)]">
                        {{ $t('content.templateCurrent') }}
                        <span class="font-semibold text-[var(--text-strong)]">{{ chosenTemplateLabel }}</span>
                    </span>
                    <span class="text-[12px] font-semibold text-[var(--gold)]">{{ $t('common.change') }}</span>
                </button>

                <!-- Por qué no salió nada: antes esto se quedaba en el
                     servidor sin avisar nunca. Ella lo probó con "Fondo de
                     color" y "Combinación" y no vio ni un mensaje — parecía
                     que el botón no hacía nada. -->
                <p v-if="generateForm.errors.uploadIds" class="mt-3 rounded-xl bg-[var(--danger-hover)] p-3 text-center text-[13px] font-medium text-[var(--danger)]">
                    {{ generateForm.errors.uploadIds }}
                </p>

                <p class="mt-2 text-center text-[12px] font-normal text-[var(--text-faint)]">
                    {{ $t('content.noAiOnWork') }}
                </p>
            </div>

            <!-- Tus fotos: queda todo lo que subió "para editar", ya se haya
                 usado o no. Antes, apenas se armaba un post, la foto
                 desaparecía sin dejar rastro — acá se puede volver a ver y,
                 si ya se usó, ponerla de nuevo en la cola con un toque. -->
            <div v-if="recentEdits.length > 0" class="rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface)] p-4">
                <div class="text-[15px] font-semibold text-[var(--text-strong)]">
                    {{ $t('content.recentEditsTitle') }}
                </div>
                <div class="mt-0.5 text-[12px] font-normal text-[var(--text-mute)]">
                    {{ $t('content.recentEditsHint') }}
                </div>

                <!-- Un toque mete la foto en la cola, otro la saca. Antes
                     solo se podía meter y ella se quedaba trabada. -->
                <div class="mt-3.5 grid grid-cols-5 gap-1.5">
                    <button
                        v-for="item in recentEdits"
                        :key="item.id"
                        type="button"
                        :disabled="queueForm.processing"
                        class="relative aspect-square overflow-hidden rounded-lg bg-[var(--surface-mute)] disabled:opacity-60"
                        :class="!item.used && 'ring-2 ring-[var(--gold)]'"
                        :aria-pressed="!item.used"
                        :aria-label="$t('content.togglePhoto')"
                        @click="toggleQueued(item)"
                    >
                        <img :src="item.url" alt="" class="h-full w-full object-cover" :class="item.used && 'opacity-45'" />

                        <!-- Marcada = entra en el próximo post. -->
                        <span
                            v-if="!item.used"
                            class="absolute right-1 top-1 flex h-5 w-5 items-center justify-center rounded-full bg-[var(--gold)]"
                        >
                            <Check :size="12" class="text-white" />
                        </span>
                    </button>
                </div>
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

                <!-- El estilo se aprende solo apenas sube una referencia (ver
                     ContentController::store()); esto ya no es el botón
                     principal, queda para releerlo a mano después de borrar
                     una referencia que no quería. -->
                <div v-if="references.length > 0" class="mt-3.5">
                    <p v-if="styleForm.errors.style" class="text-[13px] font-normal text-[var(--danger)]">
                        {{ styleForm.errors.style }}
                    </p>

                    <div v-else-if="contentStyle" class="flex items-center gap-2.5 rounded-xl bg-[var(--surface-alt)] px-3 py-2.5">
                        <span class="flex gap-1">
                            <span
                                v-for="c in contentStyle.colores"
                                :key="c"
                                class="h-4 w-4 rounded-full border border-[var(--border-strong)]"
                                :style="{ background: c }"
                            />
                        </span>
                        <span class="text-[12px] font-normal text-[var(--text-mute)]">
                            {{ $t('content.styleLearnedFrom', contentStyle.referencias) }}
                        </span>
                    </div>
                    <p v-else class="text-[12px] font-normal text-[var(--text-faint)]">
                        {{ $t('content.styleNotYet') }}
                    </p>

                    <button
                        type="button"
                        :disabled="styleForm.processing"
                        class="mt-2.5 flex items-center gap-1.5 text-[12px] font-medium text-[var(--text-mute)] hover:text-[var(--text-strong)] disabled:opacity-60"
                        @click="learnStyle"
                    >
                        <Wand :size="13" />
                        {{ styleForm.processing ? $t('content.learning') : $t('content.relearnStyle') }}
                    </button>
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

        <!-- Elegir a mano las fotos para "Fondo de color": tienen que ser del
             mismo trabajo, y agarrar las más viejas de la cola a ciegas no lo
             garantiza. Ver colorPickNames más arriba. -->
        <BottomSheet v-model="colorPickOpen">
            <div class="mb-4">
                <div class="text-[20px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">
                    {{ $t(colorPickIsCombo ? 'content.comboPickTitle' : 'content.colorPickTitle') }}
                </div>
                <p class="mt-1 text-[13px] font-normal text-[var(--text-mute)]">
                    {{ $t(colorPickIsCombo ? 'content.comboPickHint' : 'content.colorPickHint') }}
                </p>
            </div>

            <p v-if="waiting.length === 0" class="text-[13px] font-normal text-[var(--text-mute)]">
                {{ $t('content.colorPickEmpty') }}
            </p>

            <div v-else class="grid grid-cols-4 gap-1.5">
                <button
                    v-for="item in waiting"
                    :key="item.id"
                    type="button"
                    class="relative aspect-square overflow-hidden rounded-lg bg-[var(--surface-mute)]"
                    :class="colorPickSelected.includes(item.id) ? 'ring-2 ring-[var(--gold)]' : ''"
                    :disabled="!colorPickSelected.includes(item.id) && colorPickSelected.length >= colorPickMax"
                    @click="toggleColorPick(item.id)"
                >
                    <img :src="item.url" alt="" class="h-full w-full object-cover" :class="!colorPickSelected.includes(item.id) && colorPickSelected.length >= colorPickMax ? 'opacity-40' : ''" />

                    <!-- El color que ya se le leyó a esa foto, de un vistazo. -->
                    <span
                        class="absolute left-1 top-1 h-3.5 w-3.5 rounded-full border border-white/80"
                        :style="{ background: item.colorHex || 'transparent' }"
                    />

                    <span v-if="colorPickSelected.includes(item.id)" class="absolute inset-0 flex items-center justify-center bg-black/30">
                        <Check :size="20" class="text-white" />
                    </span>
                </button>
            </div>

            <!-- En "Fondo de color" más de un nombre es la alarma de la
                 mezcla que causó la confusión la vez pasada. En
                 "Combinación" es justo lo que se busca, así que nunca se
                 pinta de alarma ahí. -->
            <p
                v-if="colorPickNames.length > 0"
                class="mt-3 text-[13px] font-medium"
                :class="colorPickWarn ? 'text-[var(--danger)]' : 'text-[var(--text-mute)]'"
            >
                {{ colorPickNames.join(' · ') }}
            </p>

            <button
                type="button"
                :disabled="colorPickSelected.length < 2"
                class="mt-4 w-full rounded-xl bg-[var(--btn-bg)] py-3.5 text-[15px] font-semibold text-white hover:bg-[var(--btn-hover)] disabled:opacity-40"
                @click="confirmColorPick"
            >
                {{ $t('content.colorPickConfirm') }}<template v-if="colorPickSelected.length > 0"> ({{ colorPickSelected.length }})</template>
            </button>
        </BottomSheet>

        <!-- ¿Como cuál de tus referencias? Ella lo pidió directo: en vez de
             que el sistema mezcle todas sus referencias en un estilo
             promedio, poder elegir UNA puntual para copiar esa. -->
        <BottomSheet v-model="templateOpen">
            <div class="mb-5">
                <div class="text-[20px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">
                    {{ $t('content.templateTitle') }}
                </div>
                <p class="mt-1 text-[13px] font-normal text-[var(--text-mute)]">{{ $t('content.templateHint') }}</p>
            </div>

            <button
                type="button"
                :disabled="generateForm.processing"
                class="mb-3 flex w-full items-center gap-3.5 rounded-2xl border border-[var(--gold-border)] bg-[var(--gold-soft)] p-4 text-left disabled:opacity-60"
                @click="chooseTemplate(null)"
            >
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[var(--surface)]">
                    <Sparkles :size="19" class="text-[var(--gold)]" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-[15px] font-bold text-[var(--text-strong)]">{{ $t('content.templateSurprise') }}</span>
                    <span class="mt-0.5 block text-[12px] font-normal text-[var(--text-mute)]">{{ $t('content.templateSurpriseHint') }}</span>
                </span>
            </button>

            <div class="grid grid-cols-4 gap-1.5">
                <button
                    v-for="ref in references"
                    :key="ref.id"
                    type="button"
                    :disabled="generateForm.processing"
                    class="relative aspect-square overflow-hidden rounded-lg bg-[var(--surface-mute)] disabled:opacity-60"
                    :aria-label="$t('content.useAsTemplate')"
                    @click="chooseTemplate(ref.id)"
                >
                    <video v-if="ref.kind === 'video'" :src="ref.url" muted playsinline preload="metadata" class="h-full w-full object-cover" />
                    <img v-else :src="ref.url" alt="" class="h-full w-full object-cover" />
                    <span
                        v-if="ref.kind === 'video'"
                        class="absolute inset-0 flex items-center justify-center bg-black/30"
                    >
                        <Clapperboard :size="15" class="text-white" />
                    </span>
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

            <Textarea v-model="noteForm.note" :label="$t('content.noteLabel')" :rows="4" />

            <p class="mt-2 text-[12px] font-normal leading-relaxed text-[var(--text-faint)]">
                {{ $t('content.noteSuggested') }}
            </p>

            <button
                type="button"
                :disabled="noteForm.processing"
                class="mt-4 w-full rounded-xl bg-[var(--btn-bg)] py-3.5 text-[15px] font-semibold text-white hover:bg-[var(--btn-hover)] disabled:opacity-60"
                @click="saveNote"
            >
                {{ noteForm.processing ? $t('common.saving') : $t('content.saveNote') }}
            </button>

            <button
                type="button"
                class="mt-2 w-full py-2 text-[13px] font-medium text-[var(--text-mute)] hover:underline"
                @click="noteOpen = false"
            >
                {{ $t('content.skipNote') }}
            </button>
        </BottomSheet>

        <!-- ¿De qué color quedó? El sistema propone, ella corrige. -->
        <BottomSheet v-model="colorOpen">
            <div class="mb-4 flex items-center gap-3">
                <span
                    class="h-12 w-12 shrink-0 rounded-xl border border-[var(--border-strong)]"
                    :style="{ background: colorForm.hex || '#eee' }"
                />
                <div class="min-w-0">
                    <div class="text-[20px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">
                        {{ $t('content.colorTitle') }}
                    </div>
                    <p class="mt-0.5 text-[13px] font-normal text-[var(--text-mute)]">
                        {{ $t('content.colorHint') }}
                    </p>
                </div>
            </div>

            <OutlinedInput
                id="content-color-name"
                v-model="colorForm.name"
                :label="$t('content.colorLabel')"
                clearable
            />

            <p class="mt-2 text-[12px] font-normal leading-relaxed text-[var(--text-faint)]">
                {{ $t('content.colorWhy') }}
            </p>

            <button
                type="button"
                :disabled="colorForm.processing"
                class="mt-4 w-full rounded-xl bg-[var(--btn-bg)] py-3.5 text-[15px] font-semibold text-white hover:bg-[var(--btn-hover)] disabled:opacity-60"
                @click="saveColor"
            >
                {{ colorForm.processing ? $t('common.saving') : $t('content.colorSave') }}
            </button>

            <button
                type="button"
                class="mt-2 w-full py-2 text-[13px] font-medium text-[var(--text-mute)] hover:underline"
                @click="colorOpen = false"
            >
                {{ $t('content.colorSkip') }}
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
