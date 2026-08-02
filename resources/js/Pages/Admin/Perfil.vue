<script setup>
import { computed, ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Camera, Ban, Plus } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import Input from '../../Components/ui/Input.vue';
import Textarea from '../../Components/ui/Textarea.vue';
import ToggleGroup from '../../Components/ui/ToggleGroup.vue';

const props = defineProps({
    profile: { type: Object, required: true },
    // { username, publicName, phone, email, bio, bannerPhoto, avatarPhoto, gallery: [url],
    //   maxGallery, published, activeServicesCount }
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
</script>

<template>
    <AdminLayout :provider-name="profile.publicName">
        <div class="relative h-[140px] w-full overflow-hidden bg-[#131a2a]">
            <img :src="profile.bannerPhoto" alt="Banner" class="h-full w-full object-cover opacity-75" />
            <button
                type="button"
                disabled
                aria-disabled="true"
                class="absolute bottom-3 right-3 flex items-center gap-1.5 rounded-full bg-black/55 px-3 py-1.5 text-[11px] font-bold text-white disabled:cursor-not-allowed disabled:opacity-60"
            >
                <Camera :size="12" />
                {{ $t('admin.changeCover') }}
            </button>
        </div>

        <div class="relative -mt-12 flex justify-center">
            <div class="relative h-24 w-24">
                <div class="box-border h-24 w-24 rounded-full bg-[var(--surface)] p-[3px] shadow-[0_10px_25px_rgba(15,23,42,0.15)]">
                    <div class="box-border h-full w-full overflow-hidden rounded-full border-[3px] border-[var(--green-text)] bg-[var(--surface-mute)]">
                        <img :src="profile.avatarPhoto" :alt="profile.publicName" class="h-full w-full object-cover" />
                    </div>
                </div>
                <button
                    type="button"
                    disabled
                    aria-disabled="true"
                    class="absolute -bottom-0.5 -right-0.5 flex h-7.5 w-7.5 items-center justify-center rounded-full border-2 border-[var(--surface)] bg-[var(--btn-bg)] disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <Camera :size="14" class="text-white" />
                </button>
            </div>
        </div>

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
                        v-for="(photo, index) in profile.gallery"
                        :key="photo"
                        class="relative aspect-square overflow-hidden rounded-2xl bg-[var(--surface-mute)]"
                    >
                        <img :src="photo" :alt="`Muestra ${index + 1}`" class="h-full w-full object-cover" />
                        <button
                            type="button"
                            disabled
                            aria-disabled="true"
                            class="absolute right-1.5 top-1.5 flex h-5.5 w-5.5 items-center justify-center rounded-full bg-black/60 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <Ban :size="11" class="text-white" />
                        </button>
                    </div>
                    <button
                        v-if="profile.gallery.length < profile.maxGallery"
                        type="button"
                        disabled
                        aria-disabled="true"
                        class="flex aspect-square flex-col items-center justify-center gap-1 rounded-2xl border-2 border-dashed border-[var(--border-strong)] text-[var(--text-faint)] disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <Plus :size="20" />
                        <span class="text-[9px] font-extrabold uppercase tracking-wide">{{ $t('admin.add') }}</span>
                    </button>
                </div>
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
    </AdminLayout>
</template>
