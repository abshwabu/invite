import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { i18n } from './i18n';
import VueKonva from 'vue-konva';

const appName = import.meta.env.VITE_APP_NAME || 'Invite';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        if (props.initialPage.props.locale) {
            i18n.global.locale.value = props.initialPage.props.locale;
        }

        const vueApp = createApp({ render: () => h(App, props) });

        vueApp.use(plugin)
            .use(ZiggyVue)
            .use(i18n)
            .use(VueKonva);

        return vueApp.mount(el);
    },
    progress: {
        color: '#D4AF37',
    },
});
