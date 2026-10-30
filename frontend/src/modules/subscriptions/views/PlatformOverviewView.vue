<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { apiErrorMessage } from '../../../shared/api/errorMessage';
import {
    subscriptionService,
    type PlatformMetrics,
    type PlatformPayment,
    type PlatformSubscription,
} from '../services/subscriptionService';

const metrics = ref<PlatformMetrics | null>(null);
const subscriptions = ref<PlatformSubscription[]>([]);
const payments = ref<PlatformPayment[]>([]);
const loading = ref(false);
const error = ref('');

async function load() {
    loading.value = true;
    error.value = '';
    try {
        [metrics.value, subscriptions.value, payments.value] = await Promise.all([
            subscriptionService.platformMetrics(),
            subscriptionService.platformSubscriptions(),
            subscriptionService.platformPayments(),
        ]);
    } catch (exception: unknown) {
        error.value = apiErrorMessage(
            exception,
            'No se pudieron cargar las métricas comerciales. Verificá el acceso Superadmin.',
        );
    } finally {
        loading.value = false;
    }
}

function amount(value: string, currency = 'ARS') {
    return new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency,
    }).format(Number(value));
}

onMounted(load);
</script>

<template>
    <div class="page-container">
        <div class="page-heading">
            <div>
                <span class="eyebrow">Administración de plataforma</span>
                <h1>Resumen comercial</h1>
                <p>Ingresos cobrados y estimaciones se muestran por separado.</p>
            </div>
            <div class="d-flex ga-2">
                <v-btn to="/platform" variant="tonal" color="secondary" rounded="lg">Resumen</v-btn>
                <v-btn to="/platform/plans" variant="outlined" color="secondary" rounded="lg">Planes</v-btn>
                <v-btn to="/platform/users" variant="outlined" color="secondary" rounded="lg">Usuarios y roles</v-btn>
            </div>
        </div>

        <v-alert v-if="error" type="error" class="mb-5" rounded="lg">{{ error }}</v-alert>
        <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-5" />

        <template v-if="metrics">
            <v-row>
                <v-col cols="12" sm="6" lg="3">
                    <v-card class="surface-card metric-card">
                        <div class="metric-label">Empresas registradas</div>
                        <div class="metric-value">{{ metrics.registered_companies }}</div>
                    </v-card>
                </v-col>
                <v-col cols="12" sm="6" lg="3">
                    <v-card class="surface-card metric-card">
                        <div class="metric-label">Suscripciones activas</div>
                        <div class="metric-value">{{ metrics.active_subscriptions }}</div>
                        <div class="text-caption text-medium-emphasis">
                            {{ metrics.companies_with_active_subscriptions }} empresas
                        </div>
                    </v-card>
                </v-col>
                <v-col cols="12" sm="6" lg="3">
                    <v-card class="surface-card metric-card">
                        <div class="metric-label">Ingresos cobrados</div>
                        <div class="metric-value metric-value--small">
                            {{ amount(metrics.collected_revenue_ars) }}
                        </div>
                    </v-card>
                </v-col>
                <v-col cols="12" sm="6" lg="3">
                    <v-card class="surface-card metric-card">
                        <div class="metric-label">MRR estimado</div>
                        <div class="metric-value metric-value--small">
                            {{ amount(metrics.estimated_monthly_recurring_revenue_ars) }}
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            No representa dinero cobrado
                        </div>
                    </v-card>
                </v-col>
                <v-col cols="12" sm="4">
                    <v-card class="surface-card metric-card">
                        <div class="metric-label">Contratos pendientes</div>
                        <div class="metric-value">{{ metrics.pending_subscriptions }}</div>
                    </v-card>
                </v-col>
                <v-col cols="12" sm="4">
                    <v-card class="surface-card metric-card">
                        <div class="metric-label">Renovaciones fallidas</div>
                        <div class="metric-value">{{ metrics.failed_renewals }}</div>
                    </v-card>
                </v-col>
                <v-col cols="12" sm="4">
                    <v-card class="surface-card metric-card">
                        <div class="metric-label">Cancelaciones</div>
                        <div class="metric-value">{{ metrics.canceled_subscriptions }}</div>
                    </v-card>
                </v-col>
            </v-row>

            <v-row class="mt-1">
                <v-col cols="12" lg="6">
                    <v-card class="surface-card">
                        <v-card-title>Distribución por plan y estado</v-card-title>
                        <v-table density="comfortable">
                            <thead>
                                <tr><th>Plan</th><th>Estado</th><th>Suscripciones</th></tr>
                            </thead>
                            <tbody>
                                <tr v-for="entry in metrics.plan_distribution" :key="`${entry.plan_id}-${entry.status}`">
                                    <td>{{ entry.plan_name }}</td>
                                    <td>{{ entry.status }}</td>
                                    <td>{{ entry.subscriptions }}</td>
                                </tr>
                                <tr v-if="metrics.plan_distribution.length === 0">
                                    <td colspan="3" class="text-medium-emphasis">Sin suscripciones registradas.</td>
                                </tr>
                            </tbody>
                        </v-table>
                    </v-card>
                </v-col>
                <v-col cols="12" lg="6">
                    <v-card class="surface-card">
                        <v-card-title>Suscripciones recientes</v-card-title>
                        <v-table density="comfortable">
                            <thead>
                                <tr><th>Empresa</th><th>Plan</th><th>Estado</th><th>Importe</th></tr>
                            </thead>
                            <tbody>
                                <tr v-for="subscription in subscriptions" :key="subscription.id">
                                    <td>{{ subscription.company.name }}</td>
                                    <td>{{ subscription.plan.name }}</td>
                                    <td>{{ subscription.status }}</td>
                                    <td>
                                        {{ amount(
                                            subscription.terms_snapshot.amount,
                                            subscription.terms_snapshot.currency,
                                        ) }}
                                    </td>
                                </tr>
                                <tr v-if="subscriptions.length === 0">
                                    <td colspan="4" class="text-medium-emphasis">Sin contratos registrados.</td>
                                </tr>
                            </tbody>
                        </v-table>
                    </v-card>
                </v-col>
            </v-row>

            <v-card class="surface-card mt-5">
                <v-card-title>Operaciones de pago recientes</v-card-title>
                <v-table density="comfortable">
                    <thead>
                        <tr><th>Empresa</th><th>Plan</th><th>Tipo</th><th>Estado</th><th>Importe</th><th>Fecha de cobro</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="payment in payments" :key="payment.id">
                            <td>{{ payment.subscription.company.name }}</td>
                            <td>{{ payment.subscription.plan.name }}</td>
                            <td>{{ payment.kind }}</td>
                            <td>{{ payment.status }}</td>
                            <td>{{ amount(payment.amount, payment.currency) }}</td>
                            <td>{{ payment.paid_at ? new Date(payment.paid_at).toLocaleDateString('es-AR') : '—' }}</td>
                        </tr>
                        <tr v-if="payments.length === 0">
                            <td colspan="6" class="text-medium-emphasis">Sin pagos registrados.</td>
                        </tr>
                    </tbody>
                </v-table>
            </v-card>
            <p class="text-caption text-medium-emphasis mt-4">
                Datos calculados al {{ new Date(metrics.as_of).toLocaleString('es-AR') }}.
            </p>
        </template>
    </div>
</template>
