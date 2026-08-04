<script setup>
import { computed, ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Camera, Ban, Plus } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import Input from '../../Components/ui/Input.vue';
import Textarea from '../../Components/ui/Textarea.vue';
import ToggleGroup from '../../Components/ui/ToggleGroup.vue';
import ConfirmDialog from '../../Components/ui/ConfirmDialog.vue';

const props = defineProps({
    profile: { type: Object, required: true },
    // { username, publicName, phone, email, bio, bannerPhoto, avatarPhoto,
    //   gallery: [{ id, url }], maxGallery, published, activeServicesCount }
});

const { t } = useI18n();

const form = useForm({
    username: props.profile.username,
    publicName: props.profile.publicName,
    phone: props.profile.phone,
    bio: props.profile.bio,
});

function submit() {
    form.put('/admin/perfil', { preserveScroll: true, preserveState: true });
}

const publishOptions = computed(() => [
    { value: 'public', label: t('admin.publishStatePublic') },
    { value: 'hidden', label: t('admin.publishStateHidden') },
]);

const canPublish = computed(() => props.profile.published || props.profile.activeServicesCount > 0);
const publishValue = computed(() => (props.profile.published ? 'public' : 'hidden'));
const publishProcessing = ref(false);

function togglePublish(value) {
    publishProcessing.value = true;
    router.patch(
        '/admin/perfil/publicacion',
        { published: value === 'public' },
        { preserveScroll: true, preserveState: true, onFinish: () => { publishProcessing.value = false; } },
    );
}

// Client-side size check is convenience only — instant feedback instead of
// waiting out an 8MB upload just to get the same rejection from the
// server, which validates this regardless.
const MAX_UPLOAD_BYTES = 8 * 1024 * 1024;

const avatarInput = ref(null);
const bannerInput = ref(null);
const galleryInput = ref(null);

const avatarForm = useForm({ photo: null });
const bannerForm = useForm({ photo: null });
const galleryForm = useForm({ photo: null });

function uploadPhoto(form, url) {
    return (event) => {
        const input = event.target;
        const file = input.files?.[0];
        if (!file) return;

        form.clearErrors();

        if (file.size > MAX_UPLOAD_BYTES) {
            form.setError('photo', t('admin.photoTooLarge'));
            input.value = '';
            return;
        }

        form.photo = file;
        form.post(url, {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                input.value = '';
            },
        });
    };
}

const onAvatarSelected = uploadPhoto(avatarForm, '/admin/perfil/avatar');
const onBannerSelected = uploadPhoto(bannerForm, '/admin/perfil/portada');
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
    <AdminLayout :provider-name="profile.publicName">
        <div class="relative h-[140px] w-full overflow-hidden bg-[#131a2a]">
            <img :src="profile.bannerPhoto" alt="Banner" class="h-full w-full object-cover opacity-75" />
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
                class="absolute bottom-3 right-3 flex items-center gap-1.5 rounded-full bg-black/55 px-3 py-1.5 text-[11px] font-bold text-white disabled:cursor-not-allowed disabled:opacity-60"
                @click="bannerInput?.click()"
            >
                <Camera :size="12" />
                {{ bannerForm.processing ? `${bannerForm.progress?.percentage ?? 0}%` : $t('admin.changeCover') }}
            </button>
        </div>

        <div class="relative -mt-12 flex justify-center pointer-events-none">
            <div class="relative h-24 w-24 pointer-events-auto">
                <div class="box-border h-24 w-24 rounded-full bg-[var(--surface)] p-[3px] shadow-[0_10px_25px_rgba(15,23,42,0.15)]">
                    <div class="box-border h-full w-full overflow-hidden rounded-full border-[3px] border-[var(--green-text)] bg-[var(--surface-mute)]">
                        <img :src="profile.avatarPhoto" :alt="profile.publicName" class="h-full w-full object-cover" />
                    </div>
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
                    class="absolute -bottom-0.5 -right-0.5 flex h-7.5 w-7.5 items-center justify-center rounded-full border-2 border-[var(--surface)] bg-[var(--btn-bg)] disabled:cursor-not-allowed disabled:opacity-60"
                    @click="avatarInput?.click()"
                >
                    <span v-if="avatarForm.processing" class="text-[8px] font-extrabold text-white"
                        >{{ avatarForm.progress?.percentage ?? 0 }}%</span
                    >
                    <Camera v-else :size="14" class="text-white" />
                </button>
            </div>
        </div>

        <p
            v-if="avatarForm.errors.photo || bannerForm.errors.photo"
            class="px-6 pt-2 text-center text-xs font-semibold text-[var(--danger)]"
        >
            {{ avatarForm.errors.photo || bannerForm.errors.photo }}
        </p>

        <div class="flex flex-col gap-4 p-6 pb-8">
            <div>
                <Input v-model="form.username" :label="$t('admin.username')" />
                <p v-if="form.errors.username" class="mt-1.5 text-xs font-semibold text-[var(--danger)]">{{ form.errors.username }}</p>
            </div>
            <div>
                <Input v-model="form.publicName" :label="$t('admin.publicName')" />
                <p v-if="form.errors.publicName" class="mt-1.5 text-xs font-semibold text-[var(--danger)]">{{ form.errors.publicName }}</p>
            </div>
            <div>
                <Input v-model="form.phone" :label="$t('admin.phone')" type="tel" />
                <p v-if="form.errors.phone" class="mt-1.5 text-xs font-semibold text-[var(--danger)]">{{ form.errors.phone }}</p>
            </div>
            <div>
                <Input :model-value="profile.email" :label="$t('admin.email')" type="email" disabled />
                <p class="mt-1.5 text-[11px] font-semibold text-[var(--text-faint)]">{{ $t('admin.emailReadOnly') }}</p>
            </div>
            <div>
                <Textarea v-model="form.bio" :label="$t('admin.bio')" :rows="4" />
                <p v-if="form.errors.bio" class="mt-1.5 text-xs font-semibold text-[var(--danger)]">{{ form.errors.bio }}</p>
            </div>

            <div>
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-[var(--text-faint)]">{{
                        $t('admin.publishProfile')
                    }}</span>
                </div>
                <ToggleGroup
                    :model-value="publishValue"
                    :options="publishOptions"
                    :disabled="publishProcessing || !canPublish"
                    @update:model-value="togglePublish"
                />
                <p class="mt-1.5 text-[11px] font-semibold text-[var(--text-faint)]">{{ $t('admin.publishProfileHint') }}</p>
                <p v-if="!canPublish" class="mt-1.5 text-[11px] font-semibold text-[var(--amber-text)]">
                    {{ $t('admin.publishBlockedNoServices') }}
                </p>
                <p v-else-if="profile.published && profile.activeServicesCount === 0" class="mt-1.5 text-[11px] font-semibold text-[var(--amber-text)]">
                    {{ $t('admin.publishedWithoutServices') }}
                </p>
            </div>

            <div>
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-[var(--text-faint)]">{{
                        $t('admin.sampleImages')
                    }}</span>
                    <span class="text-[10px] font-bold text-[var(--text-faint)]"
                        >{{ profile.gallery.length }} / {{ profile.maxGallery }}</span
                    >
                </div>
                <div class="grid grid-cols-3 gap-2.5">
                    <div
                        v-for="photo in profile.gallery"
                        :key="photo.id"
                        class="relative aspect-square overflow-hidden rounded-2xl bg-[var(--surface-mute)]"
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
                        class="flex aspect-square flex-col items-center justify-center gap-1 rounded-2xl border-2 border-dashed border-[var(--border-strong)] text-[var(--text-faint)] hover:border-[var(--text-faint)] disabled:cursor-not-allowed disabled:opacity-60"
                        @click="galleryInput?.click()"
                    >
                        <template v-if="galleryForm.processing">
                            <span class="text-[11px] font-extrabold">{{ galleryForm.progress?.percentage ?? 0 }}%</span>
                        </template>
                        <template v-else>
                            <Plus :size="20" />
                            <span class="text-[9px] font-extrabold uppercase tracking-wide">{{ $t('admin.add') }}</span>
                        </template>
                    </button>
                </div>
                <p v-if="galleryForm.errors.photo" class="mt-1.5 text-xs font-semibold text-[var(--danger)]">
                    {{ galleryForm.errors.photo }}
                </p>
            </div>

            <button
                type="button"
                :disabled="form.processing"
                class="w-full rounded-xl bg-[var(--btn-bg)] py-3.5 text-sm font-bold text-white hover:bg-[var(--btn-hover)] disabled:cursor-not-allowed disabled:opacity-60"
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
    </AdminLayout>
</template>
