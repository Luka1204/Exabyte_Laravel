<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../modules/identity/stores/authStore';

const auth = useAuthStore();
const router = useRouter();
const drawer = ref(true);

async function logout() {
    await auth.logout();
    await router.replace('/login');
}

async function selectCompany(companyId: number) {
    auth.setActiveCompany(companyId);

    if (router.currentRoute.value.path === '/companies') {
        await router.push('/categories');
    }
}
</script>

<template>
    <v-app class="app-shell">
        <v-navigation-drawer v-model="drawer" class="nav-drawer" width="260">
            <div class="d-flex align-center ga-3 px-5 py-6">
                <span class="brand-mark">EX</span>
                <div>
                    <div class="text-h6 font-weight-bold text-secondary">Exabyte</div>
                    <div class="text-caption text-medium-emphasis">Gestión comercial</div>
                </div>
            </div>
            <div class="nav-section-label">Espacio de trabajo</div>
            <v-list nav density="comfortable">
                <v-list-item
                    prepend-icon="mdi-point-of-sale"
                    title="Punto de venta"
                    to="/sales"
                />
                <v-list-item
                    prepend-icon="mdi-package-variant-closed"
                    title="Productos"
                    to="/products"
                />
                <v-list-item
                    prepend-icon="mdi-warehouse"
                    title="Inventario"
                    to="/inventory"
                />
                <v-list-item
                    prepend-icon="mdi-shape-outline"
                    title="Categorías"
                    to="/categories"
                />
                <v-list-item
                    prepend-icon="mdi-chart-box-outline"
                    title="Reportes"
                    to="/reports"
                />
                <v-list-item
                    prepend-icon="mdi-domain"
                    title="Empresas"
                    to="/companies"
                />
                <v-list-item
                    prepend-icon="mdi-view-grid-outline"
                    title="Planes"
                    to="/plans"
                />
                <v-list-item
                    prepend-icon="mdi-credit-card-outline"
                    title="Suscripción"
                    to="/subscription"
                    v-if="auth.activeCompany?.role === 'owner'"
                />
            </v-list>
        </v-navigation-drawer>

        <v-app-bar class="app-bar" flat>
            <v-app-bar-nav-icon aria-label="Abrir navegación" @click="drawer = !drawer" />
            <v-toolbar-title class="text-subtitle-1 font-weight-bold text-secondary">
                {{ auth.activeCompany?.name ?? 'Seleccioná una empresa' }}
            </v-toolbar-title>
            <template #append>
                <v-select
                    v-if="auth.companies.length > 0"
                    :model-value="auth.activeCompanyId"
                    :items="auth.companies"
                    item-title="name"
                    item-value="id"
                    label="Empresa"
                    density="compact"
                    hide-details
                    class="mr-5"
                    style="max-width: 220px"
                    @update:model-value="selectCompany"
                />

                <v-menu>
                    <template #activator="{ props }">
                        <v-btn v-bind="props" variant="text" class="text-none">
                            <v-avatar color="secondary" size="34" class="mr-2">
                                <span class="text-caption text-white font-weight-bold">
                                    {{ auth.user?.name?.slice(0, 1).toUpperCase() }}
                                </span>
                            </v-avatar>
                            <span class="d-none d-sm-inline">{{ auth.user?.name }}</span>
                            <v-icon end icon="mdi-chevron-down" />
                        </v-btn>
                    </template>
                    <v-list>
                        <v-list-item
                            prepend-icon="mdi-logout"
                            title="Cerrar sesión"
                            @click="logout"
                        />
                    </v-list>
                </v-menu>
            </template>
        </v-app-bar>

        <v-main>
            <router-view />
        </v-main>
    </v-app>
</template>