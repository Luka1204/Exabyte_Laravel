<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { apiErrorMessage } from '../../../shared/api/errorMessage';
import { useAuthStore } from '../../identity/stores/authStore';
import {
    subscriptionService,
    type CompanySubscription,
    type Plan,
} from '../services/subscriptionService';

const router = useRouter();
const auth = useAuthStore();
const plans = ref<Plan[]>([]);
const subscription = ref<CompanySubscription | null>(null);
const billingPeriod = ref<'monthly' | 'annual'>('monthly');
const loading = ref(true);
const selectingPlan = ref<number | null>(null);
const error = ref('');
const success = ref('');

const changeableStatuses = ['trialing', 'active', 'past_due', 'grace'];

function canChangeSubscription() {
    return subscription.value !== null && changeableStatuses.includes(subscription.value.status);
}

async function loadPlans() {
    loading.value = true;
    error.value = '';
    try {
        plans.value = await subscriptionService.plans();
        if (auth.accessToken && auth.activeCompany?.role === 'owner') {
            subscription.value = await subscriptionService.current(auth.activeCompany.id);
        } else {
            subscription.value = null;
        }
    } catch (exception: unknown) {
        error.value = apiErrorMessage(exception, 'No se pudieron cargar los planes o la suscripción.');
    } finally {
        loading.value = false;
    }
}

async function choosePlan(plan: Plan) {
    error.value = '';
    success.value = '';
    if (!auth.accessToken) {
        await router.push({
            path: '/register',
            query: { plan: String(plan.id), period: billingPeriod.value },
        });
        return;
    }

    if (!auth.activeCompany) {
        await router.push({
            path: '/companies',
            query: { plan: String(plan.id), period: billingPeriod.value },
        });
        return;
    }

    if (auth.activeCompany.role !== 'owner') {
        error.value = 'Solo el owner de la empresa puede contratar o administrar la suscripción.';
        return;
    }

    selectingPlan.value = plan.id;
    try {
        if (canChangeSubscription() && subscription.value) {
            subscription.value = await subscriptionService.change(
                auth.activeCompany.id,
                plan.id,
                billingPeriod.value,
            );
            success.value = 'El plan y período del contrato se actualizaron. Se conserva el historial de pagos.';
            return;
        }

        const result = await subscriptionService.start(
            auth.activeCompany.id,
            plan.id,
            billingPeriod.value,
        );
        if (result.checkout_url) {
            window.location.assign(result.checkout_url);
            return;
        }
        await router.push('/subscription');
    } catch (exception: unknown) {
        error.value = apiErrorMessage(
            exception,
            canChangeSubscription()
                ? 'No se pudo actualizar el contrato. El plan, período y pagos anteriores se conservan.'
                : 'No se pudo iniciar el checkout. Tu contrato pendiente se conserva para reintentar.',
        );
    } finally {
        selectingPlan.value = null;
    }
}

function amount(plan: Plan) {
    const value = billingPeriod.value === 'annual'
        ? plan.annual_amount
        : plan.monthly_amount;
    const fractionDigits = Number(value) % 1 === 0 ? 0 : 2;

    return new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: plan.currency,
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: 2,
    }).format(Number(value));
}

onMounted(loadPlans);
</script>

<template>
    <main class="public-page">
        <header class="public-header">
            <RouterLink class="brand-link" to="/welcome">
                <span class="brand-mark">EX</span>
                <span>Exabyte</span>
            </RouterLink>
            <nav class="d-flex align-center ga-3">
                <RouterLink to="/login" class="public-nav-link">Iniciar sesión</RouterLink>
                <v-btn color="primary" rounded="lg" to="/register">Crear cuenta</v-btn>
            </nav>
        </header>

        <section class="public-section plans-intro">
            <span class="eyebrow">Planes simples, operación clara</span>
            <h1>Elegí el plan para tu comercio</h1>
            <p>Compará capacidades y límites. El importe del checkout siempre se verifica en el servidor.</p>
            <v-btn-toggle
                v-model="billingPeriod"
                mandatory
                color="primary"
                rounded="lg"
                class="mt-5"
            >
                <v-btn value="monthly">Mensual</v-btn>
                <v-btn value="annual">Anual</v-btn>
            </v-btn-toggle>
        </section>

        <v-alert v-if="error" type="error" class="public-content mb-6" rounded="lg">
            <div class="d-flex align-center justify-space-between flex-wrap ga-3">
                <span>{{ error }}</span>
                <v-btn variant="outlined" color="error" @click="loadPlans">Reintentar</v-btn>
            </div>
        </v-alert>
        <v-alert v-if="success" type="success" class="public-content mb-6" rounded="lg">
            {{ success }}
        </v-alert>
        <section v-if="loading" class="public-content py-12">
            <v-progress-linear indeterminate color="primary" />
        </section>
        <section v-else-if="plans.length" class="public-content plan-grid">
            <v-card
                v-for="plan in plans"
                :key="plan.id"
                class="plan-card surface-card"
            >
                <v-card-text class="pa-6">
                    <span class="eyebrow">{{ plan.code }}</span>
                    <h2 class="text-h5 font-weight-bold text-secondary">{{ plan.name }}</h2>
                    <p class="plan-description">{{ plan.description }}</p>
                    <div class="plan-price">
                        {{ amount(plan) }}
                        <span>/ {{ billingPeriod === 'annual' ? 'año' : 'mes' }}</span>
                    </div>
                    <p v-if="billingPeriod === 'annual'" class="text-caption text-medium-emphasis">
                        Cobro recurrente anual
                    </p>
                    <v-divider class="my-5" />
                    <div class="plan-limits">
                        <div>
                            <v-icon icon="mdi-account-group-outline" />
                            {{ plan.limits.users }} {{ plan.limits.users === 1 ? 'usuario' : 'usuarios' }}
                        </div>
                        <div>
                            <v-icon icon="mdi-domain" />
                            {{ plan.limits.branches }} {{ plan.limits.branches === 1 ? 'sucursal' : 'sucursales' }}
                        </div>
                        <div>
                            <v-icon icon="mdi-cash-register" />
                            {{ plan.limits.cash_registers }} {{ plan.limits.cash_registers === 1 ? 'caja' : 'cajas' }}
                        </div>
                        <div><v-icon icon="mdi-package-variant" /> {{ plan.limits.products ?? 'Sin límite' }} productos</div>
                    </div>
                    <v-list density="compact" class="bg-transparent px-0">
                        <v-list-item
                            v-for="benefit in plan.benefits"
                            :key="benefit"
                            :title="benefit"
                            prepend-icon="mdi-check-circle-outline"
                            class="px-0"
                        />
                    </v-list>
                    <v-btn
                        color="primary"
                        block
                        size="large"
                        rounded="lg"
                        :loading="selectingPlan === plan.id"
                        :disabled="selectingPlan !== null && selectingPlan !== plan.id"
                        @click="choosePlan(plan)"
                    >
                        {{ canChangeSubscription() ? 'Cambiar a este plan' : 'Elegir plan' }}
                    </v-btn>
                </v-card-text>
            </v-card>
        </section>
        <section v-else class="public-content">
            <v-alert type="info" rounded="lg">
                En este momento no hay planes disponibles para contratar.
            </v-alert>
        </section>

        <footer class="public-footer">
            <span>Precios en ARS. El checkout recurrente se procesa en Mercado Pago.</span>
            <span>Condiciones comerciales y privacidad pendientes de revisión legal.</span>
        </footer>
    </main>
</template>
