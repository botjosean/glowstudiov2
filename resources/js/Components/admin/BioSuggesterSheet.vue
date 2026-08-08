<script setup>
import { ref, computed, watch } from 'vue';
import { X, Sparkles, RotateCcw } from '@lucide/vue';
import BottomSheet from '../ui/BottomSheet.vue';
import Chip from '../ui/Chip.vue';

const open = defineModel({ type: Boolean, default: false });
const emit = defineEmits(['use']);

/**
 * Fixed choices, never free text. It keeps the questions to three taps, and it
 * means nothing a person types can reach the model as an instruction.
 */
const AREAS = ['nails', 'hair', 'barber', 'makeup', 'lashes', 'esthetics'];
const EXPERIENCE = ['starting', 'oneToThree', 'threeToFive', 'fivePlus', 'tenPlus'];
const HIGHLIGHTS = ['warm', 'punctual', 'custom', 'premium', 'relaxing', 'affordable'];

const area = ref('');
const experience = ref('');
const highlights = ref([]);
const suggestions = ref([]);
const loading = ref(false);
const failed = ref(false);

const canGenerate = computed(() => Boolean(area.value) && Boolean(experience.value) && !loading.value);

watch(open, (isOpen) => {
    if (isOpen) {
        area.value = '';
        experience.value = '';
        highlights.value = [];
        suggestions.value = [];
        failed.value = false;
    }
});

function toggleHighlight(value) {
    const at = highlights.value.indexOf(value);
    // Capped at three: past that the bios start reading like a list of claims.
    if (at >= 0) {
        highlights.value.splice(at, 1);
    } else if (highlights.value.length < 3) {
        highlights.value.push(value);
    }
}

async function generate() {
    loading.value = true;
    failed.value = false;
    suggestions.value = [];

    try {
        const response = await fetch('/admin/perfil/biografia', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '',
                ),
            },
            body: JSON.stringify({
                area: area.value,
                experience: experience.value,
                highlights: highlights.value,
            }),
        });

        const data = await response.json();
        suggestions.value = data.suggestions ?? [];
        failed.value = suggestions.value.length === 0;
    } catch {
        failed.value = true;
    } finally {
        loading.value = false;
    }
}

function use(text) {
    emit('use', text);
    open.value = false;
}
</script>

<template>
    <BottomSheet v-model="open">
        <div class="mb-5 flex items-start justify-between gap-3">
            <div>
                <div class="text-lg font-bold text-[var(--text-strong)]">{{ $t('admin.bioAiTitle') }}</div>
                <div class="mt-0.5 text-[12px] font-medium leading-relaxed text-[var(--text-faint)]">
                    {{ $t('admin.bioAiHint') }}
                </div>
            </div>
            <button
                type="button"
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[var(--surface-mute)]"
                @click="open = false"
            >
                <X :size="16" class="text-[var(--text-mute)]" />
            </button>
        </div>

        <div v-if="!suggestions.length" class="flex flex-col gap-4">
            <div>
                <span class="mb-2 block text-[13px] font-medium text-[var(--text-mute)]">{{ $t('admin.bioAiArea') }}</span>
                <div class="flex flex-wrap gap-2">
                    <Chip v-for="value in AREAS" :key="value" :active="area === value" @click="area = value">
                        {{ $t(`admin.bioArea_${value}`) }}
                    </Chip>
                </div>
            </div>

            <div>
                <span class="mb-2 block text-[13px] font-medium text-[var(--text-mute)]">{{ $t('admin.bioAiExperience') }}</span>
                <div class="flex flex-wrap gap-2">
                    <Chip v-for="value in EXPERIENCE" :key="value" :active="experience === value" @click="experience = value">
                        {{ $t(`admin.bioExp_${value}`) }}
                    </Chip>
                </div>
            </div>

            <div>
                <span class="mb-2 block text-[13px] font-medium text-[var(--text-mute)]">{{ $t('admin.bioAiHighlights') }}</span>
                <div class="flex flex-wrap gap-2">
                    <Chip
                        v-for="value in HIGHLIGHTS"
                        :key="value"
                        :active="highlights.includes(value)"
                        @click="toggleHighlight(value)"
                    >
                        {{ $t(`admin.bioHl_${value}`) }}
                    </Chip>
                </div>
            </div>

            <p v-if="failed" class="text-[13px] font-normal text-[var(--danger)]">{{ $t('admin.bioAiFailed') }}</p>

            <button
                type="button"
                :disabled="!canGenerate"
                class="flex w-full items-center justify-center gap-1.5 rounded-xl bg-[var(--btn-bg)] py-3.5 text-[15px] font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                @click="generate"
            >
                <Sparkles :size="15" />
                {{ loading ? $t('admin.bioAiWorking') : $t('admin.bioAiGenerate') }}
            </button>
        </div>

        <div v-else class="flex flex-col gap-3">
            <span class="text-[13px] font-medium text-[var(--text-mute)]">{{ $t('admin.bioAiPick') }}</span>
            <button
                v-for="(text, index) in suggestions"
                :key="index"
                type="button"
                class="rounded-2xl border border-[var(--border-strong)] p-4 text-left text-[14px] font-normal leading-relaxed text-[var(--text-body)] hover:border-[var(--text-strong)]"
                @click="use(text)"
            >
                {{ text }}
            </button>
            <button
                type="button"
                class="flex w-full items-center justify-center gap-1.5 rounded-xl border border-[var(--border-strong)] py-3 text-[14px] font-semibold text-[var(--text-body)]"
                @click="suggestions = []"
            >
                <RotateCcw :size="14" />
                {{ $t('admin.bioAiAgain') }}
            </button>
            <p class="text-[12px] font-normal leading-relaxed text-[var(--text-faint)]">{{ $t('admin.bioAiEditable') }}</p>
        </div>
    </BottomSheet>
</template>
