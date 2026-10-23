<script setup lang="ts">
import { useRouter } from 'vue-router';
import { useAuthStore } from '../modules/identity/stores/authStore';

const auth = useAuthStore();
const router = useRouter();

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
    <v-app>
        <v-navigation-drawer>
            <v-list nav>
                <v-list-item
                    prepend-icon="mdi-view-dashboard"
                    title="Inicio"
                    to="/"
                />

                <v-list-item
                    prepend-icon="mdi-shape"
                    title="Categorías"
                    to="/categories"
                />

                <v-list-item
                    prepend-icon="mdi-domain"
                    title="Empresas"
                    to="/companies"
                />
            </v-list>
        </v-navigation-drawer>

        <v-app-bar title="Exabyte">
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
                    class="mr-4"
                    @update:model-value="selectCompany"
                />

                <span class="mr-3">
                    {{ auth.user?.name }}
                </span>

                <v-btn
                    icon="mdi-logout"
                    aria-label="Cerrar sesión"
                    @click="logout"
                />
            </template>
        </v-app-bar>

        <v-main>
            <router-view />
        </v-main>
    </v-app>
</template>