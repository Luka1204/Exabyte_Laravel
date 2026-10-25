import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from './modules/identity/stores/authStore';

import 'vuetify/styles';
import '@mdi/font/css/materialdesignicons.css';
import './style.css';

import { createVuetify } from 'vuetify';
import * as components from 'vuetify/components';
import * as directives from 'vuetify/directives';

import App from './App.vue';
import { routes } from './app/routes';

const vuetify = createVuetify({
    components,
    directives,
    theme: {
        defaultTheme: 'exabyte',
        themes: {
            exabyte: {
                dark: false,
                colors: {
                    primary: '#C62828',
                    secondary: '#333333',
                    info: '#6B7280',
                    success: '#23846a',
                    warning: '#c47a19',
                    error: '#8E1B1B',
                    background: '#F7F7F8',
                    surface: '#ffffff',
                },
            },
        },
    },
    icons: {
        defaultSet: 'mdi',
    },
});

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to) => {
    const auth = useAuthStore();

    if (to.path === '/' && !auth.accessToken) {
        return '/welcome';
    }

    if (to.matched.some((record) => record.meta.requiresAuth)
        && !auth.accessToken) {
        return '/login';
    }

    if (to.meta.guestOnly && auth.accessToken) {
        if (to.path === '/register') {
            return '/plans';
        }

        return auth.activeCompany ? '/categories' : '/companies';
    }
});

createApp(App)
    .use(createPinia())
    .use(router)
    .use(vuetify)
    .mount('#app');