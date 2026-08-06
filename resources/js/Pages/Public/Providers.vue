<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Search, LayoutList } from '@lucide/vue';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import Chip from '../../Components/ui/Chip.vue';

const props = defineProps({
    providers: { type: Array, required: true }, // [{ id, slug, name, bio, photo, servicesCount, availableNow, mobile }]
});

const query = ref('');
const filter = ref('all');

const filters = [
    { value: 'all', key: 'providers.filterAll' },
    { value: 'available', key: 'providers.filterAvailable' },
    { value: 'mobile', key: 'providers.filterMobile' },
];

const filteredProviders = computed(() =>
    props.providers.filter((p) => {
        const matchesQuery =
            !query.value || p.name.toLowerCase().includes(query.value.toLowerCase()) || p.bio.toLowerCase().includes(query.value.toLowerCase());
        const matchesFilter =
            filter.value === 'all' ||
            (filter.value === 'available' && p.availableNow) ||
            (filter.value === 'mobile' && p.mobile);
        return matchesQuery && matchesFilter;
    }),
);
</script>

<template>
    <PublicLayout>
        <div class="border-b border-[var(--surface-mute)] p-4">
            <div class="flex items-center gap-2.5 rounded-xl border border-[var(--border-strong)] bg-[var(--surface-alt)] px-3.5 py-2.5">
                <Search :size="16" class="text-[var(--text-faint)]" />
                <input
                    v-model="query"
                    type="text"
                    :placeholder="$t('providers.searchPlaceholder')"
                    class="w-full bg-transparent text-[13px] font-semibold text-[var(--text-strong)] placeholder:font-medium placeholder:text-[var(--text-faint)] focus:outline-none"
                />
            </div>
        </div>

        <div class="flex flex-wrap gap-2 px-6 pt-5">
            <Chip v-for="f in filters" :key="f.value" :active="filter === f.value" @click="filter = f.value">
                {{ $t(f.key) }}
            </Chip>
        </div>

        <div class="px-6 pb-8 pt-5">
            <div class="mb-3.5 flex items-center justify-between">
                <span class="text-[13px] font-medium text-[var(--text-mute)]">{{
                    $t('providers.title')
                }}</span>
                <span class="text-[12px] font-medium text-[var(--text-mute)]"
                    >{{ filteredProviders.length }} {{ $t('providers.profilesLabel') }}</span
                >
            </div>
            <div class="flex flex-col gap-3">
                <div
                    v-for="provider in filteredProviders"
                    :key="provider.id"
                    class="flex min-w-0 items-center justify-between gap-3 rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface)] p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)]"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="h-13 w-13 shrink-0 overflow-hidden rounded-full bg-[var(--surface-mute)]">
                            <img :src="provider.photo" :alt="provider.name" class="h-full w-full object-cover" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-[15px] font-semibold text-[var(--text-strong)]">{{ provider.name }}</div>
                            <div class="mt-0.5 truncate text-[13px] font-normal text-[var(--text-mute)]">
                                {{ provider.bio }}
                            </div>
                            <div class="mt-1.5 inline-flex items-center gap-1.5 rounded-full bg-[var(--surface-mute)] px-2.5 py-1">
                                <LayoutList :size="11" class="text-[var(--text-mute)]" />
                                <span class="text-[11px] font-medium tracking-wide text-[var(--text-mute)]"
                                    >{{ provider.servicesCount }} {{ $t('providers.servicesLabel') }}</span
                                >
                            </div>
                        </div>
                    </div>
                    <Link
                        :href="`/p/${provider.slug}`"
                        class="shrink-0 rounded-xl bg-[var(--btn-bg)] px-4 py-2.5 text-[13px] font-medium text-white hover:bg-[var(--btn-hover)]"
                        >{{ $t('providers.view') }}</Link
                    >
                </div>
                <p v-if="filteredProviders.length === 0" class="py-8 text-center text-sm font-medium text-[var(--text-mute)]">
                    {{ $t('providers.empty') }}
                </p>
            </div>
        </div>
    </PublicLayout>
</template>
