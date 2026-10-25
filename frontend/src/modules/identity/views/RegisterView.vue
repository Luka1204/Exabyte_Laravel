<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { apiErrorMessage } from '../../../shared/api/errorMessage';
import { authService } from '../services/authService';
import { subscriptionService } from '../../subscriptions/services/subscriptionService';

const route = useRoute();
const router = useRouter();
const registrationForm = ref<{ validate: () => Promise<{ valid: boolean }> } | null>(null);
const name = ref('');
const email = ref('');
const companyName = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const loading = ref(false);
const error = ref('');
const registered = ref(false);
const planId = computed(() => {
    const value = Number(route.query.plan);
    return Number.isSafeInteger(value) && value > 0 ? value : null;
});
const billingPeriod = computed<'monthly' | 'annual'>(() => (
    route.query.period === 'annual' ? 'annual' : 'monthly'
));
const passwordRules = [
    (value: string) => value.length >= 12 || 'Usá al menos 12 caracteres.',
    (value: string) => /[A-Za-z]/.test(value) && /\d/.test(value)
        || 'Incluí letras y números.',
];

async function submit() {
    if (loading.value) {
        return;
    }

    loading.value = true;
    error.value = '';
    try {
        if (!registrationForm.value) {
            error.value = 'No se pudo validar el formulario. Actualizá la página e intentá otra vez.';
            return;
        }

        const { valid } = await registrationForm.value.validate();
        if (!valid) {
            return;
        }

        const result = await authService.register({
            name: name.value.trim(),
            email: email.value.trim(),
            password: password.value,
            password_confirmation: passwordConfirmation.value,
            company_name: companyName.value.trim(),
        });
        registered.value = true;

        if (planId.value) {
            try {
                const checkout = await subscriptionService.start(
                    result.company.id,
                    planId.value,
                    billingPeriod.value,
                );
                if (checkout.checkout_url) {
                    window.location.assign(checkout.checkout_url);
                    return;
                }
            } catch (exception: unknown) {
                error.value = apiErrorMessage(
                    exception,
                    'La cuenta y empresa se crearon, pero no pudimos iniciar el checkout. Podés reintentarlo desde Planes.',
                );
                return;
            }
        }

        await router.replace(planId.value ? '/subscription' : '/plans');
    } catch (exception: unknown) {
        error.value = apiErrorMessage(exception, 'No se pudo crear la cuenta.');
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <main class="auth-page">
        <section class="auth-card surface-card">
            <RouterLink to="/welcome" class="brand-link mb-8">
                <span class="brand-mark">EX</span>
                <span>Exabyte</span>
            </RouterLink>
            <span class="eyebrow">Empezá hoy</span>
            <h1 class="text-h4 font-weight-bold text-secondary">Crear cuenta</h1>
            <p class="text-medium-emphasis mb-6">
                Tu cuenta será titular de la empresa y de su suscripción.
            </p>

            <v-alert v-if="error" type="error" role="alert" class="mb-5" rounded="lg">
                {{ error }}
            </v-alert>
            <v-alert v-if="registered && error" type="info" class="mb-5" rounded="lg">
                Tu cuenta ya fue creada y la sesión sigue activa.
            </v-alert>

            <v-form ref="registrationForm" @submit.prevent="submit">
                <v-text-field
                    v-model="name"
                    label="Tu nombre"
                    autocomplete="name"
                    variant="outlined"
                    rounded="lg"
                    :rules="[(value: string) => !!value || 'Ingresá tu nombre.']"
                    :disabled="loading || registered"
                />
                <v-text-field
                    v-model="email"
                    label="Correo electrónico"
                    type="email"
                    autocomplete="email"
                    variant="outlined"
                    rounded="lg"
                    :rules="[
                        (value: string) => !!value || 'Ingresá tu correo.',
                        (value: string) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) || 'Ingresá un correo válido.',
                    ]"
                    :disabled="loading || registered"
                />
                <v-text-field
                    v-model="companyName"
                    label="Nombre del comercio"
                    autocomplete="organization"
                    variant="outlined"
                    rounded="lg"
                    :rules="[(value: string) => !!value || 'Ingresá el nombre del comercio.']"
                    :disabled="loading || registered"
                />
                <v-text-field
                    v-model="password"
                    label="Contraseña"
                    type="password"
                    autocomplete="new-password"
                    variant="outlined"
                    rounded="lg"
                    :rules="passwordRules"
                    :disabled="loading || registered"
                />
                <v-text-field
                    v-model="passwordConfirmation"
                    label="Repetir contraseña"
                    type="password"
                    autocomplete="new-password"
                    variant="outlined"
                    rounded="lg"
                    :rules="[
                        (value: string) => !!value || 'Repetí la contraseña.',
                        (value: string) => value === password || 'Las contraseñas no coinciden.',
                    ]"
                    :disabled="loading || registered"
                />
                <v-btn
                    v-if="!registered"
                    type="submit"
                    color="primary"
                    block
                    size="large"
                    rounded="lg"
                    :loading="loading"
                >
                    Crear cuenta
                </v-btn>
                <v-btn
                    v-else
                    color="primary"
                    block
                    size="large"
                    rounded="lg"
                    to="/plans"
                >
                    Ver planes y continuar
                </v-btn>
            </v-form>
            <p class="text-caption text-medium-emphasis mt-5">
                Al continuar aceptás las condiciones comerciales y la política de privacidad,
                pendientes de aprobación legal antes de operar comercialmente.
            </p>
            <div class="text-body-2 mt-5">
                ¿Ya tenés cuenta? <RouterLink to="/login">Iniciá sesión</RouterLink>
            </div>
        </section>
    </main>
</template>
