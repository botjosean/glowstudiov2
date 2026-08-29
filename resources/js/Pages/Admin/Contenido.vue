<script setup>
import { computed, ref, onBeforeUnmount } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Sparkles, Pin, ChevronRight, Check, Download, X } from '@lucide/vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import BottomSheet from '../../Components/ui/BottomSheet.vue';
import Textarea from '../../Components/ui/Textarea.vue';
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
    referenceCount: { type: Number, required: true },
    posts: { type: Array, required: true }, // [{ id, url, caption, hashtags, rating }]
    collagePhotos: { type: Number, required: true },
});

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

function pickFiles(event) {
    const files = Array.from(event.target.files ?? []).slice(0, 10);
    if (files.length === 0) return;

    releasePreviews();
    chosen.value = files;
    previews.value = files.map((file) => URL.createObjectURL(file));
    purposeOpen.value = true;

    // Permite volver a elegir el mismo archivo: sin esto, el input no
    // dispara change la segunda vez.
    event.target.value = '';
}

function send(purpose, note = '') {
    uploadForm.purpose = purpose;
    uploadForm.note = note;
    uploadForm.photos = chosen.value;

    uploadForm.post('/admin/contenido/subir', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            haptics.success();
            releasePreviews();
            chosen.value = [];
            purposeOpen.value = false;
            noteOpen.value = false;
            uploadForm.reset();
        },
        onError: () => haptics.error(),
    });
}

function chooseEdit() {
    send('edit');
}

function chooseReference() {
    purposeOpen.value = false;
    noteOpen.value = true;
}

const enoughForCollage = computed(() => props.waiting.length >= props.collagePhotos);

function buildCollage() {
    if (!enoughForCollage.value || generateForm.processing) return;

    generateForm.layout = 'collage_4';
    generateForm.uploadIds = props.waiting.slice(0, props.collagePhotos).map((item) => item.id);

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
                accept="image/*"
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

                <button
                    type="button"
                    :disabled="!enoughForCollage || generateForm.processing"
                    class="mt-3.5 w-full rounded-xl bg-[var(--btn-bg)] py-3.5 text-[15px] font-semibold text-white hover:bg-[var(--btn-hover)] disabled:cursor-not-allowed disabled:opacity-60"
                    @click="buildCollage"
                >
                    {{ generateForm.processing ? $t('content.building') : $t('content.buildCollage') }}
                </button>
                <p v-if="!enoughForCollage" class="mt-2 text-center text-[12px] font-normal text-[var(--text-faint)]">
                    {{ $t('content.needMore', { count: collagePhotos - waiting.length }) }}
                </p>
                <p class="mt-2 text-center text-[12px] font-normal text-[var(--text-faint)]">
                    {{ $t('content.noAiOnWork') }}
                </p>
            </div>

            <!-- Referencias acumuladas -->
            <div class="flex items-center gap-3 rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface)] p-4">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[var(--gold-soft)]">
                    <Pin :size="17" class="text-[var(--gold-text)]" />
                </span>
                <div class="min-w-0 flex-1">
                    <div class="text-[15px] font-semibold text-[var(--text-strong)]">
                        {{ $t('content.referencesTitle', referenceCount) }}
                    </div>
                    <div class="mt-0.5 text-[12px] font-normal text-[var(--text-mute)]">
                        {{ $t('content.referencesHint') }}
                    </div>
                </div>
            </div>

            <!-- Posts armados -->
            <div v-if="posts.length > 0" class="flex flex-col gap-3">
                <div class="pt-1 text-[13px] font-semibold uppercase tracking-wide text-[var(--text-faint)]">
                    {{ $t('content.postsTitle') }}
                </div>

                <div
                    v-for="post in posts"
                    :key="post.id"
                    class="overflow-hidden rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface)] shadow-[0_1px_2px_rgba(0,0,0,0.04)]"
                >
                    <img :src="post.url" alt="" class="aspect-square w-full object-cover" />

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
        </div>

        <!-- ¿Editar o referencia? -->
        <BottomSheet v-model="purposeOpen">
            <div class="mb-5">
                <div class="text-[20px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">
                    {{ $t('content.purposeTitle', chosen.length) }}
                </div>
                <p class="mt-1 text-[13px] font-normal text-[var(--text-mute)]">{{ $t('content.purposeHint') }}</p>
            </div>

            <div class="mb-5 grid grid-cols-4 gap-1.5">
                <img v-for="(url, i) in previews.slice(0, 8)" :key="i" :src="url" alt="" class="aspect-square w-full rounded-lg object-cover" />
            </div>

            <div class="flex flex-col gap-2.5">
                <button
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
    </AdminLayout>
</template>
