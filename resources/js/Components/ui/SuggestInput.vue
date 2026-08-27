<script setup>
/**
 * OutlinedInput mas un desplegable de sugerencias sacadas de lo que ella
 * misma ya escribio antes. Nada de un catalogo generico inventado: escribir
 * "Balayage" de nuevo salta directo a como lo llamo la primera vez, en vez
 * de tipearlo entero otra vez cada mes.
 */
import { computed, ref } from 'vue';
import OutlinedInput from './OutlinedInput.vue';

const props = defineProps({
    label: { type: String, required: true },
    id: { type: String, required: true },
    error: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    clearable: { type: Boolean, default: false },
    suggestions: { type: Array, default: () => [] },
    maxSuggestions: { type: Number, default: 5 },
});

const model = defineModel({ type: String, default: '' });
const focused = ref(false);

function normalize(text) {
    return String(text).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

const filtered = computed(() => {
    const needle = normalize(model.value.trim());
    if (needle === '') return [];

    const seen = new Set();
    const matches = [];
    for (const candidate of props.suggestions) {
        const normalized = normalize(candidate);
        if (normalized === needle || seen.has(normalized) || !normalized.includes(needle)) continue;
        seen.add(normalized);
        matches.push(candidate);
        if (matches.length >= props.maxSuggestions) break;
    }
    return matches;
});

const showSuggestions = computed(() => focused.value && filtered.value.length > 0);

function pick(value) {
    model.value = value;
    focused.value = false;
}
</script>

<template>
    <div class="relative">
        <OutlinedInput
            :id="id"
            v-model="model"
            :label="label"
            :error="error"
            :placeholder="placeholder"
            :clearable="clearable"
            autocomplete="off"
            @focus="focused = true"
            @blur="focused = false"
        />
        <div
            v-if="showSuggestions"
            class="absolute inset-x-0 top-full z-10 mt-1.5 overflow-hidden rounded-xl border border-[var(--border-strong)] bg-[var(--surface)] shadow-[0_8px_24px_rgba(15,23,42,0.14)]"
        >
            <button
                v-for="suggestion in filtered"
                :key="suggestion"
                type="button"
                class="flex w-full items-center px-4 py-3 text-left text-[14px] font-medium text-[var(--text-strong)] last:border-none hover:bg-[var(--surface-alt)]"
                @mousedown.prevent="pick(suggestion)"
            >
                {{ suggestion }}
            </button>
        </div>
    </div>
</template>
