<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { authService } from '../services/authService';

const router = useRouter();
const email = ref('');
const password = ref('');
const loading = ref(false);
const error = ref('');

async function submit() {
    loading.value = true;
    error.value = '';

    try {
        await authService.login(email.value, password.value);
        await router.push('/categories');
    } catch {
        error.value = 'No se pudo iniciar sesión. Revisá tus credenciales.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <v-container class="fill-height" style="max-width: 480px">
        <v-card class="pa-6" width="100%">
            <v-card-title class="text-h5 mb-4">
                Exabyte
            </v-card-title>

            <v-alert
                v-if="error"
                type="error"
                class="mb-4"
            >
                {{ error }}
            </v-alert>

            <v-form @submit.prevent="submit">
                <v-text-field
                    v-model="email"
                    label="Correo electrónico"
                    type="email"
                    autocomplete="username"
                    required
                />

                <v-text-field
                    v-model="password"
                    label="Contraseña"
                    type="password"
                    autocomplete="current-password"
                    required
                />

                <v-btn
                    type="submit"
                    color="primary"
                    block
                    :loading="loading"
                >
                    Iniciar sesión
                </v-btn>
            </v-form>
        </v-card>
    </v-container>
</template>