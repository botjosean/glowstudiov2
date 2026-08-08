<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import { Camera, Ban, Plus, Sparkles } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import Input from '../../Components/ui/Input.vue';
import Textarea from '../../Components/ui/Textarea.vue';
import ToggleGroup from '../../Components/ui/ToggleGroup.vue';
import ConfirmDialog from '../../Components/ui/ConfirmDialog.vue';
import Collapse from '../../Components/ui/Collapse.vue';
import PublicLinkCard from '../../Components/admin/PublicLinkCard.vue';
import BioSuggesterSheet from '../../Components/admin/BioSuggesterSheet.vue';
import { useOnboardingReturn } from '../../composables/useOnboardingReturn';

const props = defineProps({
    profile: { type: Object, required: true },
    // { username, publicName, phone, email, bio, isMobile, serviceArea, addressLine,
    //   bannerPhoto, avatarPhoto, gallery: [{ id, url }], maxGallery, published, activeServicesCount }
    publicUrl: { type: String, required: true },
});

const { t } = useI18n();
const { returnToInicio } = useOnboardingReturn();

const form = useForm({
    username: props.profile.username,
    publicName: props.profile.publicName,
    phone: props.profile.phone,
    bio: props.profile.bio,
    isMobile: props.profile.isMobile,
    serviceArea: props.profile.serviceArea,
    addressLine: props.profile.addressLine,
});

function submit() {
    form.put('/admin/perfil', { preserveScroll: true, preserveState: true, onSuccess: returnToInicio });
}

// One collapsible per topic, so the page reads as short questions instead of
// one endless form. The first incomplete section starts open; coming from the
// checklist's "publish" step (?abrir=publicacion) opens that one instead.
const sectionDone = computed(() => ({
    info: Boolean(props.profile.publicName) && Boolean(props.profile.bio),
    ubicacion: Boolean(props.profile.isMobile ? props.profile.serviceArea : props.profile.addressLine),
    fotos: props.profile.gallery.length >= props.profile.maxGallery,
    publicacion: props.profile.published,
}));

const page = usePage();

function initialOpenSection() {
    const order = ['info', 'ubicacion', 'fotos', 'publicacion'];
    // ?abrir=<seccion> gana sobre "la primera incompleta": el checklist manda
    // a la persona a una sección concreta y abrirle otra sería desorientarla.
    const asked = order.find((key) => page.url.includes(`abrir=${key}`));

    return asked ?? order.find((key) => !sectionDone.value[key]) ?? null;
}

const openSections = reactive({
    info: false,
    ubicacion: false,
    fotos: false,
    publicacion: false,
});
const first = initialOpenSection();
if (first) openSections[first] = true;

// A validation error inside a collapsed section would be invisible — open
// every section that has one.
const errorSection = {
    username: 'info',
    publicName: 'info',
    phone: 'info',
    bio: 'info',
    serviceArea: 'ubicacion',
    addressLine: 'ubicacion',
};

watch(
    () => form.errors,
    (errors) => {
        for (const field of Object.keys(errors)) {
            const section = errorSection[field];
            if (section) openSections[section] = true;
        }
    },
    { deep: true },
);

const publishOptions = computed(() => [
    { value: 'public', label: t('admin.publishStatePublic') },
    { value: 'hidden', label: t('admin.publishStateHidden') },
]);

const locationOptions = computed(() => [
    { value: 'studio', label: t('admin.locationStudio') },
    { value: 'mobile', label: t('admin.locationMobile') },
]);

const locationValue = computed({
    get: () => (form.isMobile ? 'mobile' : 'studio'),
    set: (value) => {
        form.isMobile = value === 'mobile';
    },
});

const canPublish = computed(() => props.profile.published || props.profile.activeServicesCount > 0);
const publishValue = computed(() => (props.profile.published ? 'public' : 'hidden'));
const publishProcessing = ref(false);

function togglePublish(value) {
    publishProcessing.value = true;
    router.patch(
        '/admin/perfil/publicacion',
        { published: value === 'public' },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (value === 'public') returnToInicio();
            },
            onFinish: () => { publishProcessing.value = false; },
        },
    );
}

// Client-side size check is convenience only — instant feedback instead of
// waiting out a large upload just to get the same rejection from the
// server, which validates this regardless.
//
// This MUST stay in step with UploadProviderPhotoRequest's File::image()->max()
// — when the server cap moved to 20 MB and this one didn't, the browser
// rejected 8-20 MB phone photos the server would have happily accepted, and
// the error quoted a limit that was no longer real. The megabyte figure is
// interpolated into the message from here so the two can't drift again.
const MAX_UPLOAD_MB = 20;
const MAX_UPLOAD_BYTES = MAX_UPLOAD_MB * 1024 * 1024;

const avatarInput = ref(null);
const bannerInput = ref(null);
const galleryInput = ref(null);

const avatarForm = useForm({ photo: null });
const bannerForm = useForm({ photo: null });
const galleryForm = useForm({ photo: null });

// The photo error renders as plain text with no dismiss button, and Inertia
// keeps form errors around until the next submit — so a single rejection used
// to sit on screen indefinitely, long after the provider had moved on. Expire
// it on a timer the way FlashMessage already does for flash messages.
const PHOTO_ERROR_TIMEOUT_MS = 6000;
const photoErrorTimers = new WeakMap();

function expirePhotoError(form) {
    clearTimeout(photoErrorTimers.get(form));
    photoErrorTimers.set(
        form,
        setTimeout(() => form.clearErrors('photo'), PHOTO_ERROR_TIMEOUT_MS),
    );
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
            // Server-side rejections (bad dimensions, unsupported type) land in
            // the same slot and need the same expiry.
            onError: () => expirePhotoError(form),
            onFinish: () => {
                input.value = '';
            },
        });
    };
}

const onAvatarSelected = uploadPhoto(avatarForm, '/admin/perfil/avatar');
const onBannerSelected = uploadPhoto(bannerForm, '/admin/perfil/portada');
const onGallerySelected = uploadPhoto(galleryForm, '/admin/perfil/galeria');

const bioSheetOpen = ref(false);

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
    <AdminLayout :provider-name="profile.publicName" :avatar-src="profile.avatarPhoto">
        <div class="relative h-[120px] w-full overflow-hidden bg-[var(--surface-mute)]">
            <img v-if="profile.bannerPhoto" :src="profile.bannerPhoto" alt="" class="h-full w-full object-cover" />
            <input
                ref="bannerInput"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                class="hidden"
                @change="onBannerSelected"
            />
            <button
                type="button"
                :disabled="bannerForm.processing"
                class="absolute bottom-3 right-3 flex items-center gap-1.5 rounded-full bg-black/55 px-3 py-1.5 text-[12px] font-medium text-white disabled:cursor-not-allowed disabled:opacity-60"
                @click="bannerInput?.click()"
            >
                <Camera :size="12" />
                {{ bannerForm.processing ? `${bannerForm.progress?.percentage ?? 0}%` : $t('admin.changeCover') }}
            </button>
        </div>

        <div class="pointer-events-none relative -mt-10 flex justify-center">
            <div class="pointer-events-auto relative h-20 w-20">
                <div class="box-border flex h-20 w-20 items-center justify-center overflow-hidden rounded-full border-[3px] border-[var(--surface)] bg-[var(--surface-mute)] shadow-[0_4px_14px_rgba(17,24,39,0.12)]">
                    <img
                        v-if="profile.avatarPhoto"
                        :src="profile.avatarPhoto"
                        :alt="profile.publicName"
                        class="h-full w-full object-cover"
                    />
                    <span v-else class="text-xl font-semibold text-[var(--text-faint)]">{{
                        profile.publicName?.charAt(0)?.toUpperCase() ?? '·'
                    }}</span>
                </div>
                <input
                    ref="avatarInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="hidden"
                    @change="onAvatarSelected"
                />
                <button
                    type="button"
                    :disabled="avatarForm.processing"
                    class="absolute -bottom-0.5 -right-0.5 flex h-7 w-7 items-center justify-center rounded-full border-2 border-[var(--surface)] bg-[var(--btn-bg)] disabled:cursor-not-allowed disabled:opacity-60"
                    @click="avatarInput?.click()"
                >
                    <span v-if="avatarForm.processing" class="text-[8px] font-bold text-white"
                        >{{ avatarForm.progress?.percentage ?? 0 }}%</span
                    >
                    <Camera v-else :size="13" class="text-white" />
                </button>
            </div>
        </div>

        <p
            v-if="avatarForm.errors.photo || bannerForm.errors.photo"
            class="px-6 pt-2 text-center text-[13px] font-normal text-[var(--danger)]"
        >
            {{ avatarForm.errors.photo || bannerForm.errors.photo }}
        </p>

        <div class="flex flex-col gap-3 p-5 pb-8">
            <div class="pb-2 pt-1 text-center">
                <h1 class="text-[22px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">
                    {{ $t('admin.profileTitle') }}
                </h1>
                <p class="mt-1 text-[13px] font-normal text-[var(--text-mute)]">{{ $t('admin.profileSubtitle') }}</p>
            </div>

            <PublicLinkCard :url="publicUrl" :published="profile.published" />

            <Collapse
                v-model="openSections.info"
                :title="$t('admin.sectionInfo')"
                :hint="profile.publicName || $t('admin.sectionInfoHint')"
                :done="sectionDone.info"
            >
                <div class="flex flex-col gap-4">
                    <div>
                        <Input v-model="form.username" :label="$t('admin.username')" />
                        <p v-if="form.errors.username" class="mt-1.5 text-[13px] font-normal text-[var(--danger)]">{{ form.errors.username }}</p>
                    </div>
                    <div>
                        <Input v-model="form.publicName" :label="$t('admin.publicName')" />
                        <p v-if="form.errors.publicName" class="mt-1.5 text-[13px] font-normal text-[var(--danger)]">{{ form.errors.publicName }}</p>
                    </div>
                    <div>
                        <Input v-model="form.phone" :label="$t('admin.phone')" type="tel" />
                        <p v-if="form.errors.phone" class="mt-1.5 text-[13px] font-normal text-[var(--danger)]">{{ form.errors.phone }}</p>
                    </div>
                    <div>
                        <Input :model-value="profile.email" :label="$t('admin.email')" type="email" disabled />
                        <p class="mt-1.5 text-[12px] font-normal text-[var(--text-faint)]">{{ $t('admin.emailReadOnly') }}</p>
                    </div>
                    <div>
                        <Textarea v-model="form.bio" :label="$t('admin.bio')" :rows="4" />
                        <button
                            type="button"
                            class="mt-2 flex items-center gap-1.5 rounded-xl border border-[var(--border-strong)] px-3 py-2 text-[13px] font-semibold text-[var(--text-body)] hover:bg-[var(--surface-mute)]"
                            @click="bioSheetOpen = true"
                        >
                            <Sparkles :size="14" />
                            {{ form.bio ? $t('admin.bioAiRedo') : $t('admin.bioAiCta') }}
                        </button>
                        <p v-if="form.errors.bio" class="mt-1.5 text-[13px] font-normal text-[var(--danger)]">{{ form.errors.bio }}</p>
                    </div>
                </div>
            </Collapse>

            <Collapse
                v-model="openSections.ubicacion"
                :title="$t('admin.sectionLocation')"
                :hint="(profile.isMobile ? profile.serviceArea : profile.addressLine) || $t('admin.sectionLocationHint')"
                :done="sectionDone.ubicacion"
            >
                <ToggleGroup v-model="locationValue" :options="locationOptions" />
                <p class="mt-1.5 text-[12px] font-normal text-[var(--text-faint)]">{{ $t('admin.locationHint') }}</p>

                <div class="mt-3">
                    <Input
                        v-if="form.isMobile"
                        v-model="form.serviceArea"
                        :label="$t('admin.serviceArea')"
                        :placeholder="$t('admin.serviceAreaPlaceholder')"
                    />
                    <Input
                        v-else
                        v-model="form.addressLine"
                        :label="$t('admin.addressLine')"
                        :placeholder="$t('admin.addressLinePlaceholder')"
                    />
                    <p v-if="form.errors.serviceArea" class="mt-1.5 text-[13px] font-normal text-[var(--danger)]">
                        {{ form.errors.serviceArea }}
                    </p>
                    <p v-if="form.errors.addressLine" class="mt-1.5 text-[13px] font-normal text-[var(--danger)]">
                        {{ form.errors.addressLine }}
                    </p>
                </div>
            </Collapse>

            <Collapse
                v-model="openSections.fotos"
                :title="$t('admin.sectionPhotos')"
                :hint="profile.gallery.length ? `${profile.gallery.length} / ${profile.maxGallery}` : $t('admin.sectionPhotosHint')"
                :done="sectionDone.fotos"
            >
                <div class="grid grid-cols-3 gap-2.5">
                    <div
                        v-for="photo in profile.gallery"
                        :key="photo.id"
                        class="relative aspect-square overflow-hidden rounded-xl bg-[var(--surface-mute)]"
                    >
                        <img :src="photo.url" alt="" class="h-full w-full object-cover" />
                        <button
                            type="button"
                            class="absolute right-1.5 top-1.5 flex h-5.5 w-5.5 items-center justify-center rounded-full bg-black/60"
                            @click="askDeletePhoto(photo)"
                        >
                            <Ban :size="11" class="text-white" />
                        </button>
                    </div>
                    <input
                        ref="galleryInput"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="hidden"
                        @change="onGallerySelected"
                    />
                    <button
                        v-if="profile.gallery.length < profile.maxGallery"
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
                <p v-if="galleryForm.errors.photo" class="mt-1.5 text-[13px] font-normal text-[var(--danger)]">
                    {{ galleryForm.errors.photo }}
                </p>
            </Collapse>

            <Collapse
                v-model="openSections.publicacion"
                :title="$t('admin.publishProfile')"
                :hint="profile.published ? $t('admin.publishStatePublic') : $t('admin.publishStateHidden')"
                :done="sectionDone.publicacion"
            >
                <ToggleGroup
                    :model-value="publishValue"
                    :options="publishOptions"
                    :disabled="publishProcessing || !canPublish"
                    @update:model-value="togglePublish"
                />
                <p class="mt-1.5 text-[12px] font-normal text-[var(--text-faint)]">{{ $t('admin.publishProfileHint') }}</p>
                <p v-if="!canPublish" class="mt-1.5 text-[12px] font-normal text-[var(--amber-text)]">
                    {{ $t('admin.publishBlockedNoServices') }}
                </p>
                <p v-else-if="profile.published && profile.activeServicesCount === 0" class="mt-1.5 text-[12px] font-normal text-[var(--amber-text)]">
                    {{ $t('admin.publishedWithoutServices') }}
                </p>
            </Collapse>

            <button
                type="button"
                :disabled="form.processing"
                class="mt-2 w-full rounded-xl bg-[var(--btn-bg)] py-3.5 text-[15px] font-semibold text-white hover:bg-[var(--btn-hover)] disabled:cursor-not-allowed disabled:opacity-60"
                @click="submit"
            >
                {{ form.processing ? $t('common.saving') : $t('admin.saveChanges') }}
            </button>
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
        <BioSuggesterSheet v-model="bioSheetOpen" @use="(text) => { form.bio = text; }" />
    </AdminLayout>
</template>
