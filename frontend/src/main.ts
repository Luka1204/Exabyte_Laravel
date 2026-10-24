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
                    primary: '#c6283d',
                    secondary: '#10213f',
                    info: '#244d83',
                    success: '#23846a',
                    warning: '#c47a19',
                    error: '#c6283d',
                    background: '#f4f6fa',
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

    if (to.matched.some((record) => record.meta.requiresAuth)
        && !auth.accessToken) {
        return '/login';
    }

    if (to.meta.guestOnly && auth.accessToken) {
        return '/categories';
    }
});

createApp(App)
    .use(createPinia())
    .use(router)
    .use(vuetify)
    .mount('#app');