<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { authService } from '../services/authService';
import { useAuthStore } from '../stores/authStore';

const router = useRouter();
const auth = useAuthStore();
const email = ref('');
const password = ref('');
const loading = ref(false);
const error = ref('');

async function submit() {
    loading.value = true;
    error.value = '';

    try {
        await authService.login(email.value, password.value);
        await router.push(auth.activeCompany ? '/categories' : '/companies');
    } catch {
        error.value = 'No se pudo iniciar sesión. Revisá tus credenciales.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <main class="login-page">
        <section class="login-brand-panel">
            <div class="d-flex align-center ga-3">
                <span class="brand-mark">EX</span>
                <span class="text-h6 font-weight-bold">Exabyte</span>
            </div>
            <div class="login-brand-copy">
                <span class="eyebrow text-white">Tu negocio, en orden</span>
                <h1>Una forma más simple de gestionar.</h1>
                <p>
                    Ventas, productos e inventario en un solo lugar, para que puedas
                    enfocarte en hacer crecer tu negocio.
                </p>
            </div>
            <div class="login-footnote">Plataforma de gestión comercial Exabyte</div>
        </section>

        <section class="login-form-panel">
            <div class="login-form-content">
                <span class="eyebrow">Bienvenido de nuevo</span>
                <h2>Iniciá sesión</h2>
                <p>Ingresá tus datos para continuar a tu espacio de trabajo.</p>

                <v-alert v-if="error" type="error" class="mb-5" rounded="lg">
                    {{ error }}
                </v-alert>

                <v-form @submit.prevent="submit">
                    <v-text-field
                        v-model="email"
                        label="Correo electrónico"
                        type="email"
                        autocomplete="username"
                        prepend-inner-icon="mdi-email-outline"
                        variant="outlined"
                        rounded="lg"
                        required
                    />
                    <v-text-field
                        v-model="password"
                        label="Contraseña"
                        type="password"
                        autocomplete="current-password"
                        prepend-inner-icon="mdi-lock-outline"
                        variant="outlined"
                        rounded="lg"
                        required
                    />
                    <v-btn
                        type="submit"
                        color="primary"
                        block
                        size="large"
                        rounded="lg"
                        :loading="loading"
                        class="mt-2"
                    >
                        Iniciar sesión
                        <v-icon end icon="mdi-arrow-right" />
                    </v-btn>
                </v-form>
            </div>
        </section>
    </main>
</template>