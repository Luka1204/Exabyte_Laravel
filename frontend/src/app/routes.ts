import type { RouteRecordRaw } from 'vue-router';

import MainLayout from '../layouts/MainLayout.vue';
import LoginView from '../modules/identity/views/LoginView.vue';
import CategoriesView from '../modules/catalog/views/CategoriesView.vue';
import ProductsView from '../modules/catalog/views/ProductsView.vue';
import InventoryView from '../modules/inventory/views/InventoryView.vue';
import SalesView from '../modules/sales/views/SalesView.vue';
import SalesReportView from '../modules/sales/views/SalesReportView.vue';
import CompaniesView from '../modules/tenancy/views/CompaniesView.vue';
import RegisterView from '../modules/identity/views/RegisterView.vue';
import LandingView from '../modules/subscriptions/views/LandingView.vue';
import PlansView from '../modules/subscriptions/views/PlansView.vue';
import CompanySubscriptionView from '../modules/subscriptions/views/CompanySubscriptionView.vue';

export const routes: RouteRecordRaw[] = [
    {
        path: '/welcome',
        component: LandingView,
    },
    {
        path: '/plans',
        component: PlansView,
    },
    {
        path: '/register',
        component: RegisterView,
        meta: { guestOnly: true },
    },
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
            {
                path: 'products',
                component: ProductsView,
            },
            {
                path: 'inventory',
                component: InventoryView,
            },
            {
                path: 'sales',
                component: SalesView,
            },
            {
                path: 'reports',
                component: SalesReportView,
            },
            {
                path: 'companies',
                component: CompaniesView,
            },
            {
                path: 'subscription',
                component: CompanySubscriptionView,
            },
        ],
    },
    {
        path: '/platform',
        component: () => import('../modules/subscriptions/views/PlatformOverviewView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/platform/plans',
        component: () => import('../modules/subscriptions/views/PlatformPlansView.vue'),
        meta: { requiresAuth: true },
    },
];