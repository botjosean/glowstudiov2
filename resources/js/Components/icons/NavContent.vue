<script setup>
defineProps({
    size: { type: Number, default: 22 },
});
</script>

<!--
    Contenido: un marco de foto con un destello. Dibujado a mano al mismo
    grosor y radio que el resto de la barra (calendario, clientas, recibo,
    tienda) — un icono de otra familia se nota de inmediato al lado.
-->
<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <rect x="3.2" y="4.6" width="17.6" height="14.8" rx="2.6" />
        <path d="M3.6 15.6l4-3.6a2 2 0 0 1 2.7 0l3.4 3.1" />
        <path d="M14.2 13.9l1.5-1.3a2 2 0 0 1 2.6 0l2.1 1.8" />
        <path d="M16.4 8.1l.5 1.2 1.2.5-1.2.5-.5 1.2-.5-1.2-1.2-.5 1.2-.5.5-1.2Z" />
    </svg>
</template>
