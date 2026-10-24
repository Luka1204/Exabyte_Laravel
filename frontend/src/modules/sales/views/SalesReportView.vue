<script setup lang="ts">
import { ref, watch } from 'vue';
import { apiErrorMessage } from '../../../shared/api/errorMessage';
import { useAuthStore } from '../../identity/stores/authStore';
import { salesReportService, type SalesSummary } from '../services/salesReportService';

function localDate(date: Date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

const auth = useAuthStore();
const today = new Date();
const from = ref(localDate(new Date(
    today.getFullYear(),
    today.getMonth(),
    today.getDate() - 29,
)));
const to = ref(localDate(today));
const summary = ref<SalesSummary | null>(null);
const loading = ref(false);
const error = ref('');

async function loadSummary() {
    if (!from.value || !to.value || from.value > to.value) {
        error.value = 'Seleccioná un rango de fechas válido.';
        summary.value = null;
        return;
    }

    loading.value = true;
    error.value = '';
    try {
        summary.value = await salesReportService.summary(from.value, to.value);
    } catch (exception: unknown) {
        error.value = apiErrorMessage(exception, 'No se pudo cargar el reporte de ventas.');
        summary.value = null;
    } finally {
        loading.value = false;
    }
}

function money(value: string | number) {
    return new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS',
    }).format(Number(value));
}

watch(() => auth.activeCompanyId, loadSummary, { immediate: true });
</script>

<template>
    <div class="page-container">
        <div class="page-heading">
            <div>
                <span class="eyebrow">Análisis</span>
                <h1>Reporte de ventas</h1>
                <p>Consultá ventas, devoluciones y anulaciones del período.</p>
            </div>
            <v-btn
                variant="outlined"
                color="secondary"
                prepend-icon="mdi-refresh"
                rounded="lg"
                :loading="loading"
                @click="loadSummary"
            >
                Actualizar
            </v-btn>
        </div>

        <v-alert v-if="error" type="error" class="mb-5" rounded="lg" closable>
            {{ error }}
        </v-alert>

        <v-card class="surface-card pa-5 mb-6">
            <v-form @submit.prevent="loadSummary">
                <v-row align="center">
                    <v-col cols="12" sm="5">
                        <v-text-field
                            v-model="from"
                            label="Desde"
                            type="date"
                            variant="outlined"
                            rounded="lg"
                            hide-details
                            required
                        />
                    </v-col>
                    <v-col cols="12" sm="5">
                        <v-text-field
                            v-model="to"
                            label="Hasta"
                            type="date"
                            variant="outlined"
                            rounded="lg"
                            hide-details
                            required
                        />
                    </v-col>
                    <v-col cols="12" sm="2">
                        <v-btn
                            type="submit"
                            color="primary"
                            block
                            size="large"
                            rounded="lg"
                            :loading="loading"
                        >
                            Aplicar
                        </v-btn>
                    </v-col>
                </v-row>
            </v-form>
        </v-card>

        <v-row v-if="summary">
            <v-col cols="12" sm="6" xl="3">
                <v-card class="surface-card h-100">
                    <v-card-text class="pa-5">
                        <div class="d-flex align-center justify-space-between mb-5">
                            <span class="text-body-2 text-medium-emphasis">Ventas válidas</span>
                            <v-avatar color="blue-lighten-5" rounded="lg">
                                <v-icon color="info" icon="mdi-receipt-text-outline" />
                            </v-avatar>
                        </div>
                        <div class="text-h5 font-weight-bold text-secondary">
                            {{ money(summary.sales_total) }}
                        </div>
                        <div class="text-caption text-medium-emphasis mt-1">
                            {{ summary.sales_count }} tickets
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="12" sm="6" xl="3">
                <v-card class="surface-card h-100">
                    <v-card-text class="pa-5">
                        <div class="d-flex align-center justify-space-between mb-5">
                            <span class="text-body-2 text-medium-emphasis">Ventas netas</span>
                            <v-avatar color="green-lighten-5" rounded="lg">
                                <v-icon color="success" icon="mdi-chart-line" />
                            </v-avatar>
                        </div>
                        <div class="text-h5 font-weight-bold text-success">
                            {{ money(summary.net_sales) }}
                        </div>
                        <div class="text-caption text-medium-emphasis mt-1">
                            Ventas menos devoluciones
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="12" sm="6" xl="3">
                <v-card class="surface-card h-100">
                    <v-card-text class="pa-5">
                        <div class="d-flex align-center justify-space-between mb-5">
                            <span class="text-body-2 text-medium-emphasis">Devoluciones</span>
                            <v-avatar color="orange-lighten-5" rounded="lg">
                                <v-icon color="warning" icon="mdi-keyboard-return" />
                            </v-avatar>
                        </div>
                        <div class="text-h5 font-weight-bold text-secondary">
                            {{ money(summary.returns_total) }}
                        </div>
                        <div class="text-caption text-medium-emphasis mt-1">
                            {{ summary.returns_count }} operaciones
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="12" sm="6" xl="3">
                <v-card class="surface-card h-100">
                    <v-card-text class="pa-5">
                        <div class="d-flex align-center justify-space-between mb-5">
                            <span class="text-body-2 text-medium-emphasis">Ticket promedio</span>
                            <v-avatar color="red-lighten-5" rounded="lg">
                                <v-icon color="primary" icon="mdi-calculator-variant-outline" />
                            </v-avatar>
                        </div>
                        <div class="text-h5 font-weight-bold text-secondary">
                            {{ money(summary.average_ticket) }}
                        </div>
                        <div class="text-caption text-medium-emphasis mt-1">
                            {{ summary.voided_count }} ventas anuladas · {{ money(summary.voided_total) }}
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <v-card v-else-if="!loading && !error" class="surface-card pa-8 text-center text-medium-emphasis">
            No hay datos de ventas para mostrar en este período.
        </v-card>
        <v-progress-linear v-if="loading" indeterminate color="primary" />

        <p v-if="summary" class="text-caption text-medium-emphasis mt-5">
            Período: {{ summary.from }} al {{ summary.to }}. Las anulaciones se informan aparte y no se incluyen
            en ventas netas. El reintegro de devoluciones se contabiliza el día en que se registra.
        </p>
    </div>
</template>
