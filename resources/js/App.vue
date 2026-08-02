<script setup>
import { ref } from 'vue';

const count = ref(0);
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center gap-4 bg-[#FDFDFC] p-6 text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
        <h1 class="text-2xl font-semibold">GlowStudio V2</h1>
        <p class="text-[#706f6c] dark:text-[#A1A09A]">Vue 3 + Vite + Laravel 13</p>
        <button
            class="rounded-sm border border-[#19140035] px-5 py-1.5 text-sm hover:border-[#1915014a] dark:border-[#3E3E3A] dark:hover:border-[#62605b]"
            @click="count++"
        >
            count is {{ count }}
        </button>
    </div>
</template>
