import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const lazyPages = import.meta.glob(['./Pages/**/*.vue', '!./Pages/School/StudentStructure.vue']);
const eagerPages = import.meta.glob('./Pages/School/StudentStructure.vue', { eager: true });

const reportBootError = (error, context = 'boot') => {
    console.error(`Edaratek ${context} error`, error);

    if (typeof document === 'undefined') {
        return;
    }

    const message = error instanceof Error
        ? `${error.name}: ${error.message}`
        : String(error || 'Unknown error');

    let panel = document.getElementById('app-boot-error-panel');

    if (!panel) {
        panel = document.createElement('div');
        panel.id = 'app-boot-error-panel';
        panel.dir = 'rtl';
        panel.style.position = 'fixed';
        panel.style.inset = '1rem 1rem auto 1rem';
        panel.style.zIndex = '99999';
        panel.style.padding = '1rem 1.25rem';
        panel.style.borderRadius = '1rem';
        panel.style.border = '1px solid rgba(248, 113, 113, 0.35)';
        panel.style.background = 'rgba(127, 29, 29, 0.94)';
        panel.style.color = '#fff';
        panel.style.boxShadow = '0 18px 40px rgba(15, 23, 42, 0.35)';
        panel.style.fontSize = '14px';
        panel.style.lineHeight = '1.7';
        panel.style.whiteSpace = 'pre-wrap';
        document.body.appendChild(panel);
    }

    panel.textContent = import.meta.env.DEV
        ? `تعذر تشغيل الواجهة الآن.\n${context}: ${message}`
        : `تعذر تحميل الواجهة الآن.\nالموضع: ${context}\nالخطأ: ${message}\nيرجى تنفيذ npm run build و php artisan optimize:clear على الخادم، ثم تحديث الصفحة.`;
};

const logOptionalBootError = (error, context) => {
    console.error(`Edaratek optional ${context} error`, error);

    if (import.meta.env.DEV) {
        reportBootError(error, context);
    }
};

if (typeof window !== 'undefined') {
    window.addEventListener('error', (event) => {
        reportBootError(event.error || event.message, 'window');
    });

    window.addEventListener('unhandledrejection', (event) => {
        reportBootError(event.reason, 'promise');
    });
}

const resolveRouteHelper = () => {
    if (typeof window !== 'undefined' && typeof window.route === 'function') {
        return window.route.bind(window);
    }

    if (typeof globalThis !== 'undefined' && typeof globalThis.route === 'function') {
        return globalThis.route.bind(globalThis);
    }

    return null;
};

const resolveInertiaPage = (name) => {
    const path = `./Pages/${name}.vue`;
    const eagerPage = eagerPages[path];

    if (eagerPage) {
        return eagerPage.default ?? eagerPage;
    }

    return resolvePageComponent(path, lazyPages);
};

const initializeRuntimeEnhancements = async () => {
    try {
        const themeModule = await import('./composables/useThemeMode');
        themeModule.ensureThemeMode?.();
    } catch (error) {
        logOptionalBootError(error, 'theme');
    }

    try {
        const inputGuardModule = await import('./utils/installInputGuards');
        inputGuardModule.installInputGuards?.();
    } catch (error) {
        logOptionalBootError(error, 'input-guards');
    }
};

const bootApp = async () => {
    await initializeRuntimeEnhancements();

    const routeHelper = resolveRouteHelper();
    let actionDialogComponent = null;

    if (!routeHelper) {
        reportBootError(new Error('Ziggy route helper is unavailable from Blade @routes.'), 'ziggy');
    }

    try {
        actionDialogComponent = (await import('./Components/AppActionDialog.vue')).default ?? null;
    } catch (error) {
        logOptionalBootError(error, 'action-dialog');
    }

    createInertiaApp({
        title: (title) => `${title} - ${appName}`,
        resolve: resolveInertiaPage,
        defaults: {
            visitOptions: (href, options) => {
                const method = String(options?.method || 'get').toLowerCase();
                const isMutationRequest = ['post', 'put', 'patch', 'delete'].includes(method);

                if (!isMutationRequest) {
                    return options;
                }

                return {
                    ...options,
                    preserveScroll: options?.preserveScroll ?? true,
                    preserveState: options?.preserveState ?? true,
                };
            },
        },
        setup({ el, App, props, plugin }) {
            const app = createApp({
                render: () => h('div', { class: 'app-shell-root' }, [
                    h(App, props),
                    ...(actionDialogComponent ? [h(actionDialogComponent)] : []),
                ]),
            });

            app.use(plugin);

            if (routeHelper) {
                app.config.globalProperties.route = routeHelper;
                app.provide('route', routeHelper);
            }

            app.config.errorHandler = (error, instance, info) => {
                console.error('Edaratek Vue error', { error, instance, info });
                reportBootError(error, `vue:${info}`);
            };

            return app.mount(el);
        },
        progress: {
            color: '#4B5563',
        },
    }).catch((error) => {
        reportBootError(error, 'inertia');
    });
};

void bootApp();
