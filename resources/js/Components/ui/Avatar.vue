<script setup>
const props = defineProps({
    src: { type: String, default: '' },
    name: { type: String, default: '' },
    size: { type: Number, default: 48 },
    ring: { type: Boolean, default: false },
});

const initials = props.name
    .split(' ')
    .map((part) => part[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();
</script>

<template>
    <div
        class="shrink-0 overflow-hidden rounded-full bg-[var(--surface-mute)]"
        :class="ring && 'border-4 border-[var(--green-text)] box-border'"
        :style="{ width: `${size}px`, height: `${size}px` }"
    >
        <img v-if="src" :src="src" :alt="name" class="h-full w-full object-cover" />
        <div
            v-else
            class="flex h-full w-full items-center justify-center font-bold text-[var(--text-mute)]"
            :style="{ fontSize: `${Math.max(size * 0.32, 11)}px` }"
        >
            {{ initials }}
        </div>
    </div>
</template>
