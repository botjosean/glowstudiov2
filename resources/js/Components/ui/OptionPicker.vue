<script setup>
/**
 * Reemplaza un <select> nativo agrupado dentro de una hoja que ya está
 * abierta. En Android ese <select> lo dibuja el sistema, no la app: abre su
 * propio menú oscuro de pantalla completa que ignora los colores, la
 * tipografía y el redondeo de todo lo demás — igual que resolvía
 * ClientPicker para elegir clienta, esto ocupa el mismo espacio de la hoja
 * en vez de apilar una tercera capa encima.
 *
 * El buscador solo sale si hay más de un puñado de opciones — con la
 * categoría (12, en dos grupos) y la duración "Otra" (72, cada 5 min)
 * sirve; con media docena de chips estorbaría más de lo que ayuda.
 */
import { computed, ref } from 'vue';
import { ArrowLeft, Check, Search } from '@lucide/vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    options: { type: Array, required: true }, // [{value,label}] o [{label,options}]
    title: { type: String, required: true },
    searchPlaceholder: { type: String, default: '' },
});

const model = defineModel({ type: [String, Number], default: '' });
const emit = defineEmits(['close']);

const { t } = useI18n();
const search = ref('');

const groups = computed(() => (props.options.some((option) => Array.isArray(option.options))
    ? props.options
    : [{ label: '', options: props.options }]));

const flatCount = computed(() => groups.value.reduce((sum, group) => sum + group.options.length, 0));
const searchable = computed(() => flatCount.value > 6);

function normalize(text) {
    return String(text).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

const filteredGroups = computed(() => {
    const needle = normalize(search.value.trim());
    if (needle === '') return groups.value;

    return groups.value
        .map((group) => ({ ...group, options: group.options.filter((option) => normalize(option.label).includes(needle)) }))
        .filter((group) => group.options.length > 0);
});

const hasResults = computed(() => filteredGroups.value.length > 0);

function pick(value) {
    model.value = value;
    search.value = '';
    emit('close');
}
</script>

<template>
    <div>
        <div class="mb-4 flex items-center gap-3">
            <button
                type="button"
                :aria-label="t('common.cancel')"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full hover:bg-[var(--surface-mute)]"
                @click="emit('close')"
            >
                <ArrowLeft :size="19" class="text-[var(--text-strong)]" />
            </button>
            <span class="text-[15px] font-bold text-[var(--text-strong)]">{{ title }}</span>
        </div>

        <label v-if="searchable" class="mb-2 flex items-center gap-2.5 rounded-xl bg-[var(--surface-mute)] px-3.5 py-2.5">
            <Search :size="16" class="shrink-0 text-[var(--text-faint)]" />
            <input
                v-model="search"
                type="search"
                :placeholder="searchPlaceholder || t('common.search')"
                class="w-full bg-transparent text-[15px] text-[var(--text-strong)] placeholder:text-[var(--text-faint)] focus:outline-none"
            />
        </label>

        <div class="max-h-[340px] overflow-y-auto">
            <template v-for="group in filteredGroups" :key="group.label || 'ungrouped'">
                <div
                    v-if="group.label"
                    class="px-1 pb-1 pt-3 text-[11px] font-semibold uppercase tracking-wide text-[var(--text-faint)] first:pt-0"
                >
                    {{ group.label }}
                </div>
                <button
                    v-for="opt in group.options"
                    :key="opt.value"
                    type="button"
                    class="flex w-full items-center justify-between gap-3 border-b border-[var(--surface-mute)] px-1 py-3 text-left last:border-b-0 hover:bg-[var(--surface-alt)]"
                    @click="pick(opt.value)"
                >
                    <span class="text-[15px] font-medium text-[var(--text-strong)]">{{ opt.label }}</span>
                    <Check v-if="opt.value === model" :size="17" class="shrink-0 text-[var(--gold)]" />
                </button>
            </template>

            <p v-if="!hasResults" class="py-6 text-center text-[13px] font-normal text-[var(--text-mute)]">
                {{ t('common.noResults') }}
            </p>
        </div>
    </div>
</template>
