import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from './modules/identity/stores/authStore';

import 'vuetify/styles';
import '@mdi/font/css/materialdesignicons.css';

import { createVuetify } from 'vuetify';
import * as components from 'vuetify/components';
import * as directives from 'vuetify/directives';

import App from './App.vue';
import { routes } from './app/routes';

const vuetify = createVuetify({
    components,
    directives,
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