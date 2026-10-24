<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { authService } from '../services/authService';
import { useAuthStore } from '../stores/authStore';

const router = useRouter();
const auth = useAuthStore();
const loginForm = ref<{ validate: () => Promise<{ valid: boolean }> } | null>(null);
const email = ref('');
const password = ref('');
const showPassword = ref(false);
const loading = ref(false);
const error = ref('');
const emailRules = [
    (value: string) => !!value || 'Ingresá tu correo electrónico.',
    (value: string) => /.+@.+\..+/.test(value) || 'Ingresá un correo válido.',
];
const passwordRules = [
    (value: string) => !!value || 'Ingresá tu contraseña.',
];

async function submit() {
    if (loading.value) {
        return;
    }

    loading.value = true;
    error.value = '';

    try {
        const { valid } = await loginForm.value!.validate();
        if (!valid) {
            return;
        }

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
                <h2 id="login-heading">Iniciá sesión</h2>
                <p>Ingresá tus datos para continuar a tu espacio de trabajo.</p>

                <v-alert
                    v-if="error"
                    type="error"
                    class="mb-5"
                    rounded="lg"
                    role="alert"
                >
                    {{ error }}
                </v-alert>

                <v-form
                    ref="loginForm"
                    aria-labelledby="login-heading"
                    @submit.prevent="submit"
                >
                    <v-text-field
                        v-model="email"
                        label="Correo electrónico"
                        type="email"
                        autocomplete="username"
                        prepend-inner-icon="mdi-email-outline"
                        variant="outlined"
                        rounded="lg"
                        :rules="emailRules"
                        :disabled="loading"
                    />
                    <v-text-field
                        v-model="password"
                        label="Contraseña"
                        :type="showPassword ? 'text' : 'password'"
                        autocomplete="current-password"
                        prepend-inner-icon="mdi-lock-outline"
                        variant="outlined"
                        rounded="lg"
                        :rules="passwordRules"
                        :disabled="loading"
                    >
                        <template #append-inner>
                            <v-btn
                                :icon="showPassword ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
                                :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                                :disabled="loading"
                                variant="text"
                                density="comfortable"
                                size="small"
                                @click="showPassword = !showPassword"
                            />
                        </template>
                    </v-text-field>
                    <v-btn
                        type="submit"
                        color="primary"
                        block
                        size="large"
                        rounded="lg"
                        :loading="loading"
                        :disabled="loading"
                        class="mt-2"
                    >
                        Iniciar sesión
                        <v-icon end icon="mdi-arrow-right" />
                    </v-btn>
                </v-form>
                <div class="text-body-2 text-center mt-6">
                    ¿Todavía no tenés cuenta?
                    <RouterLink to="/plans">Conocé los planes</RouterLink>
                </div>
            </div>
        </section>
    </main>
</template>