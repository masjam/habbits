import './bootstrap';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

import * as Sentry from "@sentry/vue";

createInertiaApp({
    title: (title) => `${title} - Sistem Pantauan Habit`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });

        if (import.meta.env.VITE_SENTRY_DSN_PUBLIC) {
            Sentry.init({
                app,
                dsn: import.meta.env.VITE_SENTRY_DSN_PUBLIC,
                trackComponents: true,
            });
        }

        app.config.errorHandler = (err, vm, info) => {
            console.error('VUE ERROR HANDLER:', err, info);
            const errDiv = document.createElement('div');
            errDiv.style = "position:fixed;top:0;left:0;right:0;background:red;color:white;z-index:99999;padding:20px;font-size:16px;font-family:monospace;white-space:pre-wrap;max-height:100vh;overflow:auto;";
            errDiv.innerText = "VUE ERROR: " + err.message + "\n\n" + err.stack;
            document.body.appendChild(errDiv);
        };

        return app
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4F46E5',
    },
});

// Register PWA Service Worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').then((registration) => {
            console.log('ServiceWorker registration successful with scope: ', registration.scope);
        }).catch((error) => {
            console.error('ServiceWorker registration failed: ', error);
        });
    });
}

window.addEventListener('error', (event) => {
    const errDiv = document.createElement('div');
    errDiv.style = "position:fixed;top:0;left:0;right:0;background:orange;color:white;z-index:99999;padding:20px;font-size:16px;font-family:monospace;white-space:pre-wrap;max-height:100vh;overflow:auto;";
    errDiv.innerText = "Global Error: " + event.message + "\n\n" + (event.error ? event.error.stack : '');
    document.body.appendChild(errDiv);
});
