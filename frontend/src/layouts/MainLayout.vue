<script setup lang="ts">
import { useRouter } from 'vue-router';
import { useAuthStore } from '../modules/identity/stores/authStore';

const auth = useAuthStore();
const router = useRouter();

async function logout() {
    await auth.logout();
    await router.replace('/login');
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
            </v-list>
        </v-navigation-drawer>

        <v-app-bar title="Exabyte">
            <template #append>
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