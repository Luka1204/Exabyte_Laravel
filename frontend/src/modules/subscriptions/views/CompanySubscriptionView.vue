<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { apiErrorMessage } from '../../../shared/api/errorMessage';
import { useAuthStore } from '../../identity/stores/authStore';
import {
    subscriptionService,
    type CompanySubscription,
} from '../services/subscriptionService';

const auth = useAuthStore();
const subscription = ref<CompanySubscription | null>(null);
const loading = ref(false);
const canceling = ref(false);
const error = ref('');
const success = ref('');
const cancelDialog = ref(false);

async function load() {
    const companyId = auth.activeCompanyId;
    if (!companyId) {
        subscription.value = null;
        return;
    }

    loading.value = true;
    error.value = '';
    try {
        subscription.value = await subscriptionService.current(companyId);
    } catch (exception: unknown) {
        error.value = apiErrorMessage(exception, 'No se pudo cargar la suscripción.');
    } finally {
        loading.value = false;
    }
}

async function cancel() {
    if (!auth.activeCompanyId) {
        return;
    }
    canceling.value = true;
    error.value = '';
    success.value = '';
    try {
        subscription.value = await subscriptionService.cancel(auth.activeCompanyId);
        success.value = 'La suscripción quedó cancelada. El historial de pagos se conserva.';
        cancelDialog.value = false;
    } catch (exception: unknown) {
        error.value = apiErrorMessage(exception, 'No se pudo confirmar la cancelación.');
    } finally {
        canceling.value = false;
    }
}

function date(value: string | null) {
    if (!value) {
        return '—';
    }
    return new Intl.DateTimeFormat('es-AR', { dateStyle: 'medium' }).format(new Date(value));
}

watch(() => auth.activeCompanyId, load);
onMounted(load);
</script>

<template>
    <div class="page-container">
        <div class="page-heading">
            <div>
                <span class="eyebrow">Facturación</span>
                <h1>Suscripción</h1>
                <p>Consulta el contrato y los intentos de pago de tu empresa.</p>
            </div>
            <v-btn variant="outlined" color="secondary" rounded="lg" :loading="loading" @click="load">
                Actualizar
            </v-btn>
        </div>

        <v-alert v-if="error" type="error" class="mb-5" rounded="lg">{{ error }}</v-alert>
        <v-alert v-if="success" type="success" class="mb-5" rounded="lg">{{ success }}</v-alert>
        <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-5" />

        <v-card v-if="subscription" class="surface-card mb-6">
            <v-card-text class="pa-6">
                <div class="d-flex align-start justify-space-between flex-wrap ga-4">
                    <div>
                        <span class="eyebrow">Contrato #{{ subscription.id }}</span>
                        <h2 class="text-h5 font-weight-bold text-secondary">
                            {{ subscription.terms_snapshot.name }}
                        </h2>
                        <p class="text-medium-emphasis">
                            {{ subscription.terms_snapshot.currency }}
                            {{ subscription.terms_snapshot.amount }}
                            · {{ subscription.billing_period === 'annual' ? 'Anual' : 'Mensual' }}
                        </p>
                    </div>
                    <v-chip color="secondary" variant="tonal">{{ subscription.status }}</v-chip>
                </div>
                <v-row class="mt-3">
                    <v-col cols="12" sm="4">
                        <div class="text-caption text-medium-emphasis">Estado Mercado Pago</div>
                        <strong>{{ subscription.provider_status ?? 'Pendiente de confirmación' }}</strong>
                    </v-col>
                    <v-col cols="12" sm="4">
                        <div class="text-caption text-medium-emphasis">Vencimiento del período</div>
                        <strong>{{ date(subscription.current_period_end) }}</strong>
                    </v-col>
                    <v-col cols="12" sm="4">
                        <div class="text-caption text-medium-emphasis">Cancelada</div>
                        <strong>{{ date(subscription.canceled_at) }}</strong>
                    </v-col>
                </v-row>
                <div class="d-flex flex-wrap ga-3 mt-5">
                    <v-btn
                        v-if="subscription.status === 'pending' && subscription.checkout_url"
                        color="primary"
                        rounded="lg"
                        :href="subscription.checkout_url"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Continuar checkout
                    </v-btn>
                    <v-btn
                        v-if="['pending', 'trialing', 'active', 'past_due', 'grace'].includes(subscription.status)"
                        variant="outlined"
                        color="error"
                        rounded="lg"
                        @click="cancelDialog = true"
                    >
                        Cancelar suscripción
                    </v-btn>
                    <v-btn variant="text" to="/plans">Consultar planes</v-btn>
                </div>
            </v-card-text>
        </v-card>

        <v-card v-if="subscription" class="surface-card">
            <v-card-title class="pa-5 text-secondary">Historial de pagos</v-card-title>
            <v-divider />
            <v-table>
                <thead class="table-header">
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                        <th>Importe</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="attempt in subscription.payment_attempts" :key="attempt.id">
                        <td>{{ date(attempt.paid_at) }}</td>
                        <td>{{ attempt.kind === 'initial' ? 'Alta' : 'Renovación' }}</td>
                        <td>{{ attempt.status }}</td>
                        <td>{{ attempt.currency }} {{ attempt.amount }}</td>
                    </tr>
                    <tr v-if="subscription.payment_attempts.length === 0">
                        <td colspan="4" class="text-center text-medium-emphasis py-6">
                            Todavía no hay pagos confirmados.
                        </td>
                    </tr>
                </tbody>
            </v-table>
        </v-card>
        <v-alert v-else-if="!loading && !error" type="info" rounded="lg">
            Esta empresa todavía no tiene una suscripción.
            <RouterLink to="/plans">Consultar los planes</RouterLink>
        </v-alert>

        <v-dialog v-model="cancelDialog" max-width="480">
            <v-card class="surface-card">
                <v-card-title class="pa-5">¿Cancelar ahora?</v-card-title>
                <v-card-text>
                    La política actual aplica la cancelación inmediata. Se conservarán
                    los datos comerciales y el historial del contrato.
                </v-card-text>
                <v-card-actions class="pa-5 pt-0">
                    <v-spacer />
                    <v-btn variant="text" @click="cancelDialog = false">Volver</v-btn>
                    <v-btn color="error" :loading="canceling" @click="cancel">Confirmar cancelación</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>
