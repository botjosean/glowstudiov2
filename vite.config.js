import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                // Manrope: números más finos y elegantes que Figtree —
                // pedido del dueño el 2026-08-16 tras comparar 5 candidatas.
                //
                // Cuatro pesos, no seis. El 300 y el 800 se descargaban en cada
                // visita y no los usaba NADIE: font-light y font-extrabold
                // aparecen cero veces en toda la app (contado el 2026-08-25).
                // Eran dos archivos de fuente por visita a cambio de nada, y en
                // un celular con datos cada uno es una espera. Quitarlos no
                // cambia un píxel: si algún día hace falta uno, se agrega aquí.
                bunny('Manrope', {
                    weights: [400, 500, 600, 700],
                }),
            ],
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
