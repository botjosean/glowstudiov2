<script setup>
import { ref } from 'vue';
import { Camera, Ban, Plus } from '@lucide/vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import Input from '../../Components/ui/Input.vue';
import Textarea from '../../Components/ui/Textarea.vue';

const props = defineProps({
    profile: { type: Object, required: true },
    // { username, publicName, phone, email, bio, bannerPhoto, avatarPhoto, gallery: [url], maxGallery }
});

const form = ref({ ...props.profile, gallery: [...props.profile.gallery] });

function removePhoto(index) {
    form.value.gallery.splice(index, 1);
}
</script>

<template>
    <AdminLayout :provider-name="profile.publicName">
        <div class="relative h-[140px] w-full overflow-hidden bg-[#131a2a]">
            <img :src="form.bannerPhoto" alt="Banner" class="h-full w-full object-cover opacity-75" />
            <button
                type="button"
                class="absolute bottom-3 right-3 flex items-center gap-1.5 rounded-full bg-black/55 px-3 py-1.5 text-[11px] font-bold text-white"
            >
                <Camera :size="12" />
                {{ $t('admin.changeCover') }}
            </button>
        </div>

        <div class="relative -mt-12 flex justify-center">
            <div class="relative h-24 w-24">
                <div class="box-border h-24 w-24 rounded-full bg-[var(--surface)] p-[3px] shadow-[0_10px_25px_rgba(15,23,42,0.15)]">
                    <div class="box-border h-full w-full overflow-hidden rounded-full border-[3px] border-[var(--green-text)] bg-[var(--surface-mute)]">
                        <img :src="form.avatarPhoto" :alt="profile.publicName" class="h-full w-full object-cover" />
                    </div>
                </div>
                <button
                    type="button"
                    class="absolute -bottom-0.5 -right-0.5 flex h-7.5 w-7.5 items-center justify-center rounded-full border-2 border-[var(--surface)] bg-[var(--btn-bg)]"
                >
                    <Camera :size="14" class="text-white" />
                </button>
            </div>
        </div>

        <div class="flex flex-col gap-4 p-6 pb-8">
            <Input v-model="form.username" :label="$t('admin.username')" />
            <Input v-model="form.publicName" :label="$t('admin.publicName')" />
            <Input v-model="form.phone" :label="$t('admin.phone')" type="tel" />
            <Input v-model="form.email" :label="$t('admin.email')" type="email" />
            <Textarea v-model="form.bio" :label="$t('admin.bio')" :rows="4" />

            <div>
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-[var(--text-faint)]">{{
                        $t('admin.sampleImages')
                    }}</span>
                    <span class="text-[10px] font-bold text-[var(--text-faint)]"
                        >{{ form.gallery.length }} / {{ profile.maxGallery }}</span
                    >
                </div>
                <div class="grid grid-cols-3 gap-2.5">
                    <div
                        v-for="(photo, index) in form.gallery"
                        :key="photo"
                        class="relative aspect-square overflow-hidden rounded-2xl bg-[var(--surface-mute)]"
                    >
                        <img :src="photo" :alt="`Muestra ${index + 1}`" class="h-full w-full object-cover" />
                        <button
                            type="button"
                            class="absolute right-1.5 top-1.5 flex h-5.5 w-5.5 items-center justify-center rounded-full bg-black/60"
                            @click="removePhoto(index)"
                        >
                            <Ban :size="11" class="text-white" />
                        </button>
                    </div>
                    <button
                        v-if="form.gallery.length < profile.maxGallery"
                        type="button"
                        class="flex aspect-square flex-col items-center justify-center gap-1 rounded-2xl border-2 border-dashed border-[var(--border-strong)] text-[var(--text-faint)] hover:border-[var(--text-faint)]"
                    >
                        <Plus :size="20" />
                        <span class="text-[9px] font-extrabold uppercase tracking-wide">{{ $t('admin.add') }}</span>
                    </button>
                </div>
            </div>

            <button
                type="button"
                class="w-full rounded-xl bg-[var(--btn-bg)] py-3.5 text-sm font-bold text-white hover:bg-[var(--btn-hover)]"
            >
                {{ $t('admin.saveChanges') }}
            </button>
        </div>
    </AdminLayout>
</template>
