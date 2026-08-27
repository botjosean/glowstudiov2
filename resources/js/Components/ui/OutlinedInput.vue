<script setup>
import { computed, ref } from 'vue';
import { X } from '@lucide/vue';
import { useI18n } from 'vue-i18n';

/**
 * Outlined text field with a floating label (the label sits centered in the
 * empty field and rises into a notch on the border once the field has focus
 * or a value). This is the field style the redesign is standardizing on;
 * `ui/Input.vue` is the legacy stacked-label style, kept untouched until the
 * remaining pages migrate.
 *
 * The float is pure CSS via `:placeholder-shown`, which is why the input
 * always carries a placeholder — a single space when the caller doesn't
 * provide one. A real placeholder stays invisible until focus so it never
 * fights the label for the same spot.
 */
defineOptions({ inheritAttrs: false });

const props = defineProps({
    label: { type: String, required: true },
    // Required (not derived) so the label/error wiring survives SSR-less
    // duplicate mounts of the same form.
    id: { type: String, required: true },
    type: { type: String, default: 'text' },
    placeholder: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    error: { type: String, default: '' },
    clearable: { type: Boolean, default: false },
    prefix: { type: String, default: '' },
});

const model = defineModel({ type: [String, Number], default: '' });

const { t } = useI18n();
const inputEl = ref(null);

const showClear = computed(() => props.clearable && model.value !== '' && model.value !== null && !props.disabled);

function clear() {
    model.value = '';
    inputEl.value?.focus();
}

// <script setup> exposes nothing to a parent's template ref by default —
// SignUp.vue's step 2 needs this to move focus into the form the moment it
// appears.
defineExpose({ focus: () => inputEl.value?.focus() });
</script>

<template>
    <div>
        <div class="relative">
            <span
                v-if="prefix"
                class="pointer-events-none absolute left-4 top-1/2 flex -translate-y-1/2 items-center gap-2.5"
            >
                <span class="text-[15px] font-normal text-[var(--text-mute)]">{{ prefix }}</span>
                <span class="h-5 w-px bg-[var(--border-strong)]" />
            </span>
            <input
                :id="id"
                ref="inputEl"
                v-model="model"
                v-bind="$attrs"
                :type="type"
                :placeholder="placeholder || ' '"
                :disabled="disabled"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="error ? `${id}-error` : undefined"
                class="peer w-full rounded-xl border bg-[var(--surface)] px-4 py-[15px] text-[15px] font-semibold text-[var(--text-strong)] transition-[border-color,box-shadow] duration-150 placeholder:font-normal placeholder:text-transparent focus:border-[var(--text-strong)] focus:shadow-[inset_0_0_0_1px_var(--text-strong)] focus:placeholder:text-[var(--text-faint)] focus-visible:outline-none disabled:cursor-not-allowed disabled:bg-[var(--surface-mute)] disabled:opacity-60"
                :class="[
                    error ? 'border-[var(--danger)]' : 'border-[var(--border-strong)]',
                    prefix && 'pl-12',
                    clearable && 'pr-12',
                    type === 'number' &&
                        '[appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none',
                ]"
            />
            <label
                :for="id"
                class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 bg-[var(--surface)] px-1 text-[15px] font-normal text-[var(--text-mute)] transition-all duration-150 peer-focus:top-0 peer-focus:text-[12px] peer-focus:font-medium peer-focus:text-[var(--text-strong)] peer-[:not(:placeholder-shown)]:top-0 peer-[:not(:placeholder-shown)]:text-[12px] peer-[:not(:placeholder-shown)]:font-medium"
                :class="error && 'text-[var(--danger)] peer-focus:text-[var(--danger)]'"
            >
                {{ label }}
            </label>
            <button
                v-if="showClear"
                type="button"
                :aria-label="t('common.clear')"
                class="absolute right-3 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full text-[var(--text-faint)] hover:bg-[var(--surface-mute)] hover:text-[var(--text-mute)]"
                @mousedown.prevent
                @click="clear"
            >
                <X :size="15" />
            </button>
        </div>
        <p v-if="error" :id="`${id}-error`" class="mt-1.5 text-[13px] font-normal text-[var(--danger)]">{{ error }}</p>
    </div>
</template>
