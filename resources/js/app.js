import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { i18n } from './i18n';
// Side-effect import: applies the stored theme at boot. Without it, pages
// whose chunk doesn't use useTheme() load in light mode even with dark saved.
import './composables/useTheme';

const appName = import.meta.env.VITE_APP_NAME || 'Glowstudio VIP';

// Cross-fade every Inertia navigation with the browser's native View
// Transitions API instead of an instant swap. `viewTransition` is a per-visit
// Inertia option with no global switch, so it's set here on the one event
// every visit fires through. Skipped under prefers-reduced-motion — the CSS
// fallback in app.css can't reach these pseudo-elements with `*`, so the
// safest guard is not to request the transition at all.
document.addEventListener('inertia:before', (event) => {
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        event.detail.visit.viewTransition = true;
    }
});

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18n)
            .mount(el);
    },
});
