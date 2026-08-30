<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
// Pencil faltaba y el botón de cambiar la portada salía como un cuadrado
// oscuro vacío: Vue no dibuja un componente que no existe, y en producción
// ni siquiera avisa. Ella lo vio antes que nadie: «o sí funciona pero no se
// ve».
import { Ban, Camera, ChevronRight, Eye, MessageCircle, Pencil, Plus, Settings, Share2 } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import ConfirmDialog from '../../Components/ui/ConfirmDialog.vue';
import FloatingAction from '../../Components/ui/FloatingAction.vue';
import QrShareSheet from '../../Components/admin/QrShareSheet.vue';
import PhotoRepositionSheet from '../../Components/admin/PhotoRepositionSheet.vue';

/**
 * The Perfil tab as Booksy has it: a showcase, not a form. What the world
 * sees (cover, name, portfolio), how the business is doing (progress card,
 * three honest numbers, the bot's state), and the doors — pencil to edit in
 * Configuración, eye to preview, share with the QR. The words and switches
 * themselves live in Configuración → Información del negocio.
 */
const props = defineProps({
    providerName: { type: String, default: 'Pati' },
    bannerPhoto: { type: String, default: null },
    avatarPhoto: { type: String, default: null },
    publicName: { type: String, required: true },
    addressLabel: { type: String, default: null },
    published: { type: Boolean, default: false },
    publicUrl: { type: String, required: true },
    whatsappConnected: { type: Boolean, default: false },
    progress: { type: Object, required: true },
    // { done, total }
    stats: { type: Object, required: true },
    // { upcoming, completedMonth, salesMonth }
    gallery: { type: Array, default: () => [] },
    maxGallery: { type: Number, default: 6 },
});

const { t } = useI18n();

const shareOpen = ref(false);

const progressPct = computed(() => Math.round((props.progress.done / props.progress.total) * 100));

// ---- Cover / avatar / portfolio uploads (same endpoints as always) --------

const MAX_UPLOAD_MB = 20;
const MAX_UPLOAD_BYTES = MAX_UPLOAD_MB * 1024 * 1024;

const avatarInput = ref(null);
const bannerInput = ref(null);
const galleryInput = ref(null);

const avatarForm = useForm({ photo: null, focus_x: null, focus_y: null });
const bannerForm = useForm({ photo: null, focus_x: null, focus_y: null });
const galleryForm = useForm({ photo: null });

const PHOTO_ERROR_TIMEOUT_MS = 6000;
const photoErrorTimers = new WeakMap();

function expirePhotoError(form) {
    clearTimeout(photoErrorTimers.get(form));
    photoErrorTimers.set(form, setTimeout(() => form.clearErrors('photo'), PHOTO_ERROR_TIMEOUT_MS));
}

function uploadPhoto(form, url) {
    return (event) => {
        const input = event.target;
        const file = input.files?.[0];
        if (!file) return;

        clearTimeout(photoErrorTimers.get(form));
        form.clearErrors();

        if (file.size > MAX_UPLOAD_BYTES) {
            form.setError('photo', t('admin.photoTooLarge', { max: MAX_UPLOAD_MB }));
            expirePhotoError(form);
            input.value = '';
            return;
        }

        form.photo = file;
        form.post(url, {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onError: () => expirePhotoError(form),
            onFinish: () => {
                input.value = '';
            },
        });
    };
}

// Avatar y portada paran primero en PhotoRepositionSheet — el servidor
// recorta donde ella arrastró el dedo, no en un ancla fija (ver
// StoreProviderImage::cropToFocus). La galería sigue subiendo directo:
// son fotos de trabajo, no un retrato que haya que encuadrar.
const repositionOpen = ref(false);
const repositionFile = ref(null);
const repositionShape = ref('circle');
let pendingUpload = null;

function pickPhotoForReposition(form, url, shape) {
    return (event) => {
        const input = event.target;
        const file = input.files?.[0];
        input.value = '';
        if (!file) return;

        clearTimeout(photoErrorTimers.get(form));
        form.clearErrors();

        if (file.size > MAX_UPLOAD_BYTES) {
            form.setError('photo', t('admin.photoTooLarge', { max: MAX_UPLOAD_MB }));
            expirePhotoError(form);
            return;
        }

        pendingUpload = { form, url };
        repositionFile.value = file;
        repositionShape.value = shape;
        repositionOpen.value = true;
    };
}

function cancelReposition() {
    repositionOpen.value = false;
    pendingUpload = null;
    repositionFile.value = null;
}

function confirmReposition({ x, y }) {
    if (!pendingUpload) return;
    const { form, url } = pendingUpload;
    const file = repositionFile.value;

    repositionOpen.value = false;
    pendingUpload = null;
    repositionFile.value = null;

    form.photo = file;
    form.focus_x = x;
    form.focus_y = y;
    form.post(url, {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onError: () => expirePhotoError(form),
    });
}

const onAvatarSelected = pickPhotoForReposition(avatarForm, '/admin/perfil/avatar', 'circle');
const onBannerSelected = pickPhotoForReposition(bannerForm, '/admin/perfil/portada', 'banner');
const onGallerySelected = uploadPhoto(galleryForm, '/admin/perfil/galeria');

const deleteConfirmOpen = ref(false);
const deleteProcessing = ref(false);
const photoToDelete = ref(null);

function askDeletePhoto(photo) {
    photoToDelete.value = photo;
    deleteConfirmOpen.value = true;
}

function confirmDeletePhoto() {
    if (!photoToDelete.value) return;
    deleteProcessing.value = true;
    router.delete(`/admin/perfil/galeria/${photoToDelete.value.id}`, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            deleteProcessing.value = false;
            deleteConfirmOpen.value = false;
            photoToDelete.value = null;
        },
    });
}
</script>

<template>
    <AdminLayout :provider-name="providerName" :avatar-src="avatarPhoto">
        <!-- Overlay doors, Booksy's: pencil → change the cover, eye → preview,
             share pill. The pencil pointed at the business-info form until
             2026-08-17, which is why it briefly became a gear — but its icon
             had been dropped from the imports in the same change, so it
             rendered as an empty black square on every professional's profile.
             It now does what a pencil on a photo is expected to do. -->
        <div class="relative h-44 w-full overflow-hidden bg-[var(--banner-bg)]">
            <img v-if="bannerPhoto" :src="bannerPhoto" alt="" class="h-full w-full object-cover" />
            <input ref="bannerInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="onBannerSelected" />

            <div class="absolute left-3 top-3">
                <button
                    type="button"
                    :disabled="bannerForm.processing"
                    :aria-label="$t('admin.changeCover')"
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-black/45 backdrop-blur-sm hover:bg-black/60 disabled:opacity-60"
                    @click="bannerInput?.click()"
                >
                    <span v-if="bannerForm.processing" class="text-[11px] font-bold text-white">{{ bannerForm.progress?.percentage ?? 0 }}%</span>
                    <Pencil v-else :size="17" class="text-white" />
                </button>
            </div>
            <div class="absolute right-3 top-3 flex items-center gap-2">
                <a
                    v-if="published"
                    :href="publicUrl"
                    target="_blank"
                    rel="noopener"
                    :aria-label="$t('admin.publicLinkView')"
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-black/45 backdrop-blur-sm hover:bg-black/60"
                >
                    <Eye :size="17" class="text-white" />
                </a>
                <button
                    v-if="published"
                    type="button"
                    class="flex items-center gap-1.5 rounded-xl bg-black/45 px-3.5 py-2.5 text-[13px] font-semibold text-white backdrop-blur-sm hover:bg-black/60"
                    @click="shareOpen = true"
                >
                    {{ $t('admin.shareProfile') }}
                    <Share2 :size="14" />
                </button>
            </div>
        </div>

        <!-- Avatar overlapping the cover, Booksy-style -->
        <div class="pointer-events-none relative -mt-12 flex justify-center">
            <div class="pointer-events-auto relative h-24 w-24">
                <div class="box-border flex h-24 w-24 items-center justify-center overflow-hidden rounded-full border-4 border-[var(--surface)] bg-[var(--surface-mute)] shadow-[0_4px_14px_rgba(17,24,39,0.15)]">
                    <img v-if="avatarPhoto" :src="avatarPhoto" :alt="publicName" class="h-full w-full object-cover" />
                    <span v-else class="text-2xl font-semibold text-[var(--text-faint)]">{{ publicName?.charAt(0)?.toUpperCase() ?? '·' }}</span>
                </div>
                <input ref="avatarInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="onAvatarSelected" />
                <button
                    type="button"
                    :disabled="avatarForm.processing"
                    class="absolute -bottom-0.5 -right-0.5 flex h-8 w-8 items-center justify-center rounded-full border-2 border-[var(--surface)] bg-[var(--btn-bg)] disabled:cursor-not-allowed disabled:opacity-60"
                    @click="avatarInput?.click()"
                >
                    <span v-if="avatarForm.processing" class="text-[11px] font-bold text-white">{{ avatarForm.progress?.percentage ?? 0 }}%</span>
                    <Camera v-else :size="14" class="text-white" />
                </button>
            </div>
        </div>

        <p v-if="avatarForm.errors.photo || bannerForm.errors.photo" class="px-6 pt-2 text-center text-[13px] font-normal text-[var(--danger)]">
            {{ avatarForm.errors.photo || bannerForm.errors.photo }}
        </p>

        <div class="flex flex-col items-center px-5 pt-3">
            <span
                class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[12px] font-semibold"
                :class="published
                    ? 'bg-[var(--green-soft)] text-[var(--green-text-strong)]'
                    : 'bg-[var(--surface-mute)] text-[var(--text-mute)]'"
            >
                <span class="h-1.5 w-1.5 rounded-full" :class="published ? 'bg-[var(--green-text)]' : 'bg-[var(--text-faint)]'" />
                {{ published ? $t('admin.profilePublished') : $t('admin.publicLinkHidden') }}
            </span>
            <h1 class="mt-2 text-[24px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">{{ publicName }}</h1>
            <p v-if="addressLabel" class="mt-0.5 text-[13px] font-normal text-[var(--text-mute)]">{{ addressLabel }}</p>
        </div>

        <!-- min-h so a near-empty account (a fresh signup, before there is any
             gallery or stats to speak of) still has enough real height that
             its last cards land above the floating "Configuración" pill
             below instead of under it — dvh, not vh, so Chrome's collapsing
             address bar doesn't reintroduce the same gap on a phone. -->
        <div class="flex min-h-[60dvh] flex-col gap-3 p-5 pb-28">
            <!-- Progress card, Booksy's "Novato — X de Y" -->
            <Link
                href="/admin/inicio"
                class="rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface)] p-4 hover:border-[var(--border-strong)]"
            >
                <div class="flex items-center justify-between">
                    <span class="text-[15px] font-semibold text-[var(--text-strong)]">{{ $t('admin.settingsActivation') }}</span>
                    <span class="flex items-center gap-1 text-[13px] font-medium text-[var(--text-mute)]">
                        {{ $t('inicio.progress', { done: progress.done, total: progress.total }) }}
                        <ChevronRight :size="15" class="text-[var(--text-faint)]" />
                    </span>
                </div>
                <div class="mt-2.5 h-1.5 overflow-hidden rounded-full bg-[var(--surface-mute)]">
                    <div class="h-full rounded-full bg-[var(--gold)]" :style="{ width: `${progressPct}%` }" />
                </div>
            </Link>

            <!-- Three honest numbers -->
            <div class="grid grid-cols-3 divide-x divide-[var(--surface-mute)] rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface)] py-3.5">
                <div class="flex flex-col items-center gap-0.5">
                    <span class="text-[18px] font-bold tabular-nums text-[var(--text-strong)]">{{ stats.upcoming }}</span>
                    <span class="px-1 text-center text-[11px] font-medium text-[var(--text-mute)]">{{ $t('admin.clientStatUpcoming') }}</span>
                </div>
                <div class="flex flex-col items-center gap-0.5">
                    <span class="text-[18px] font-bold tabular-nums text-[var(--text-strong)]">{{ stats.completedMonth }}</span>
                    <span class="px-1 text-center text-[11px] font-medium text-[var(--text-mute)]">{{ $t('admin.statMonthDone') }}</span>
                </div>
                <div class="flex flex-col items-center gap-0.5">
                    <span class="text-[18px] font-bold tabular-nums text-[var(--text-strong)]">${{ stats.salesMonth.toFixed(0) }}</span>
                    <span class="px-1 text-center text-[11px] font-medium text-[var(--text-mute)]">{{ $t('admin.statMonthSales') }}</span>
                </div>
            </div>

            <!-- The WhatsApp assistant's state, at a glance -->
            <div class="flex items-center gap-3.5 rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface)] p-4">
                <span
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                    :class="whatsappConnected ? 'bg-[var(--green-soft)]' : 'bg-[var(--surface-mute)]'"
                >
                    <MessageCircle :size="18" :class="whatsappConnected ? 'text-[var(--green-text)]' : 'text-[var(--text-faint)]'" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-[14px] font-semibold text-[var(--text-strong)]">{{ $t('inicio.botTitle') }}</span>
                    <span class="block text-[12px] font-normal leading-relaxed text-[var(--text-mute)]">
                        {{ whatsappConnected ? $t('inicio.botActive') : $t('inicio.botOffline') }}
                    </span>
                </span>
            </div>

            <!-- Portafolio -->
            <div>
                <div class="flex items-baseline justify-between px-1 pb-2 pt-2">
                    <span class="text-[15px] font-bold text-[var(--text-strong)]">{{ $t('admin.portfolioTitle') }}</span>
                    <span class="text-[12px] font-medium text-[var(--text-faint)]">{{ gallery.length }} / {{ maxGallery }}</span>
                </div>
                <div class="grid grid-cols-3 gap-2.5">
                    <div
                        v-for="photo in gallery"
                        :key="photo.id"
                        class="relative aspect-square overflow-hidden rounded-xl bg-[var(--surface-mute)]"
                    >
                        <img :src="photo.url" alt="" class="h-full w-full object-cover" />
                        <button
                            type="button"
                            :aria-label="$t('admin.deletePhoto')"
                            class="absolute right-1.5 top-1.5 flex h-5.5 w-5.5 items-center justify-center rounded-full bg-black/60"
                            @click="askDeletePhoto(photo)"
                        >
                            <Ban :size="11" class="text-white" />
                        </button>
                    </div>
                    <button
                        v-if="gallery.length < maxGallery"
                        type="button"
                        :disabled="galleryForm.processing"
                        class="flex aspect-square flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-[var(--border-strong)] text-[var(--text-faint)] hover:border-[var(--text-faint)] disabled:cursor-not-allowed disabled:opacity-60"
                        @click="galleryInput?.click()"
                    >
                        <template v-if="galleryForm.processing">
                            <span class="text-[12px] font-medium">{{ galleryForm.progress?.percentage ?? 0 }}%</span>
                        </template>
                        <template v-else>
                            <Plus :size="20" />
                            <span class="text-[11px] font-medium">{{ $t('admin.add') }}</span>
                        </template>
                    </button>
                </div>
                <input ref="galleryInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="onGallerySelected" />
                <button
                    v-if="gallery.length < maxGallery"
                    type="button"
                    :disabled="galleryForm.processing"
                    class="mt-3 flex w-full items-center justify-center gap-2 rounded-xl border border-[var(--border-strong)] py-3 text-[14px] font-semibold text-[var(--text-body)] hover:bg-[var(--surface-mute)] disabled:cursor-not-allowed disabled:opacity-60"
                    @click="galleryInput?.click()"
                >
                    <Camera :size="15" />
                    {{ $t('admin.uploadFromPhone') }}
                </button>
                <p v-if="galleryForm.errors.photo" class="mt-1.5 text-[13px] font-normal text-[var(--danger)]">
                    {{ galleryForm.errors.photo }}
                </p>
            </div>
        </div>

        <ConfirmDialog
            v-model="deleteConfirmOpen"
            :title="$t('admin.confirmDeletePhotoTitle')"
            :body="$t('admin.confirmDeletePhotoBody')"
            :confirm-label="$t('admin.deletePhoto')"
            :processing="deleteProcessing"
            variant="danger"
            @confirm="confirmDeletePhoto"
        />

        <QrShareSheet v-model="shareOpen" :url="publicUrl" :provider-name="publicName" />

        <PhotoRepositionSheet
            v-model="repositionOpen"
            :file="repositionFile"
            :shape="repositionShape"
            @confirm="confirmReposition"
            @cancel="cancelReposition"
        />

        <!-- The same pill that says "Chat de ayuda" one screen further in. -->
        <FloatingAction href="/admin/ajustes" :label="$t('admin.settings')">
            <template #icon>
                <Settings :size="18" class="text-[var(--gold)]" />
            </template>
        </FloatingAction>
    </AdminLayout>
</template>
