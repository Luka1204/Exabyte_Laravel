import type { RouteRecordRaw } from 'vue-router';

import MainLayout from '../layouts/MainLayout.vue';
import LoginView from '../modules/identity/views/LoginView.vue';
import CategoriesView from '../modules/catalog/views/CategoriesView.vue';

export const routes: RouteRecordRaw[] = [
    {
        path: '/login',
        component: LoginView,
        meta: { guestOnly: true },
    },
    {
        path: '/',
        component: MainLayout,
        meta: { requiresAuth: true },
        children: [
            {
                path: '',
                redirect: '/categories',
            },
            {
                path: 'categories',
                component: CategoriesView,
            },
        ],
    },
];