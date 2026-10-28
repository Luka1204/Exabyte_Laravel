<script setup lang="ts">
import { ref, watch } from 'vue';
import { apiErrorMessage } from '../../../shared/api/errorMessage';
import { useAuthStore } from '../../identity/stores/authStore';
import {
    inventoryService,
    type InventoryMovement,
} from '../../inventory/services/inventoryService';
import { activityService, type CompanyActivityEvent } from '../services/activityService';

const auth = useAuthStore();
const tab = ref('activity');
const events = ref<CompanyActivityEvent[]>([]);
const movements = ref<InventoryMovement[]>([]);
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const loading = ref(false);
const error = ref('');
const movementsLoading = ref(false);
const movementsError = ref('');

async function loadActivity() {
    loading.value = true;
    error.value = '';
    try {
        const result = await activityService.list(page.value);
        events.value = result.data;
        lastPage.value = result.last_page;
        total.value = result.total;
    } catch (exception: unknown) {
        error.value = apiErrorMessage(exception, 'No se pudo cargar la trazabilidad.');
    } finally {
        loading.value = false;
    }
}

async function loadMovements() {
    movementsLoading.value = true;
    movementsError.value = '';
    try {
        const result = await inventoryService.movements(25);
        movements.value = result.data;
    } catch (exception: unknown) {
        movementsError.value = apiErrorMessage(
            exception,
            'No se pudieron cargar los movimientos de inventario.',
        );
    } finally {
        movementsLoading.value = false;
    }
}

function actionLabel(action: string) {
    const labels: Record<string, string> = {
        'record.created': 'Creó',
        'record.updated': 'Actualizó',
        'record.deleted': 'Eliminó',
        'status.changed': 'Cambió el estado de',
        'record.activated': 'Activó',
        'record.deactivated': 'Desactivó',
        'sale.voided': 'Anuló una venta',
        'sale.returned': 'Registró una devolución',
        'cash_session.closed': 'Cerró una caja',
    };

    return labels[action] ?? action;
}

function subjectLabel(subject: string) {
    const labels: Array<[string, string]> = [
        ['/categories', 'categoría'],
        ['/products', 'producto'],
        ['/members', 'miembro'],
        ['/companies', 'empresa'],
        ['/sales', 'venta'],
        ['/cash/session', 'caja'],
        ['/inventory/adjustments', 'movimiento de inventario'],
        ['/subscription', 'suscripción'],
    ];

    return labels.find(([path]) => subject.includes(path))?.[1] ?? subject;
}

function dateLabel(value: string) {
    return new Intl.DateTimeFormat('es-AR', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function movementLabel(type: InventoryMovement['type']) {
    return {
        adjustment: 'Ajuste',
        sale: 'Venta',
        sale_void: 'Anulación',
        sale_return: 'Devolución',
    }[type];
}

watch(() => auth.activeCompanyId, () => {
    page.value = 1;
    void loadActivity();
}, { immediate: true });

watch(tab, (value) => {
    if (value === 'inventory' && movements.value.length === 0) {
        void loadMovements();
    }
});
</script>

<template>
    <div class="page-container">
        <div class="page-heading">
            <div>
                <span class="eyebrow">Operaciones</span>
                <h1>Movimientos y trazabilidad</h1>
                <p>Historial de acciones realizadas en esta empresa.</p>
            </div>
            <v-btn
                variant="outlined"
                color="secondary"
                prepend-icon="mdi-refresh"
                rounded="lg"
                :loading="loading"
                @click="loadActivity"
            >
                Actualizar
            </v-btn>
        </div>

        <v-tabs v-model="tab" class="mb-4">
            <v-tab value="activity">Actividad</v-tab>
            <v-tab value="inventory">Movimientos de inventario</v-tab>
        </v-tabs>

        <v-window v-model="tab">
            <v-window-item value="activity">
                <v-alert v-if="error" type="error" class="mb-5" rounded="lg" closable>
                    {{ error }}
                </v-alert>
                <v-card class="surface-card">
                    <v-card-title class="pa-5 text-secondary">
                        Actividad registrada
                        <v-chip class="ml-3" size="small" variant="tonal">{{ total }} eventos</v-chip>
                    </v-card-title>
                    <v-divider />
                    <v-table>
                        <thead>
                            <tr class="table-header">
                                <th>Fecha y hora</th>
                                <th>Usuario</th>
                                <th>Acción</th>
                                <th>Elemento</th>
                                <th>Referencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!loading && events.length === 0">
                                <td colspan="5" class="py-12 text-center text-medium-emphasis">
                                    Todavía no hay movimientos registrados.
                                </td>
                            </tr>
                            <tr v-for="event in events" :key="event.id">
                                <td>{{ dateLabel(event.created_at) }}</td>
                                <td>{{ event.actor?.name ?? 'Sistema' }}</td>
                                <td>{{ actionLabel(event.action) }}</td>
                                <td>{{ subjectLabel(event.subject_type) }}</td>
                                <td>{{ event.subject_id ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </v-table>
                    <v-progress-linear v-if="loading" indeterminate color="primary" />
                    <div class="d-flex align-center justify-end ga-4 pa-4">
                        <span class="text-caption text-medium-emphasis">Página {{ page }} de {{ lastPage }}</span>
                        <v-btn
                            icon="mdi-chevron-left"
                            variant="text"
                            aria-label="Página anterior"
                            :disabled="page <= 1 || loading"
                            @click="page--; loadActivity()"
                        />
                        <v-btn
                            icon="mdi-chevron-right"
                            variant="text"
                            aria-label="Página siguiente"
                            :disabled="page >= lastPage || loading"
                            @click="page++; loadActivity()"
                        />
                    </div>
                </v-card>
            </v-window-item>
            <v-window-item value="inventory">
                <v-alert v-if="movementsError" type="error" class="mb-5" rounded="lg" closable>
                    {{ movementsError }}
                </v-alert>
                <v-card class="surface-card">
                    <v-card-title class="pa-5 text-secondary">Movimientos de stock recientes</v-card-title>
                    <v-divider />
                    <v-table>
                        <thead>
                            <tr class="table-header">
                                <th>Fecha y hora</th>
                                <th>Usuario</th>
                                <th>Producto</th>
                                <th>Tipo</th>
                                <th>Cantidad</th>
                                <th>Saldo posterior</th>
                                <th>Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!movementsLoading && movements.length === 0 && !movementsError">
                                <td colspan="7" class="py-12 text-center text-medium-emphasis">
                                    Todavía no hay movimientos de inventario.
                                </td>
                            </tr>
                            <tr v-for="movement in movements" :key="movement.id">
                                <td>{{ dateLabel(movement.created_at) }}</td>
                                <td>{{ movement.user.name }}</td>
                                <td>{{ movement.product.name }} ({{ movement.product.sku }})</td>
                                <td>{{ movementLabel(movement.type) }}</td>
                                <td>{{ movement.quantity_delta }}</td>
                                <td>{{ movement.balance_after }}</td>
                                <td>{{ movement.reason }}</td>
                            </tr>
                        </tbody>
                    </v-table>
                    <v-progress-linear v-if="movementsLoading" indeterminate color="primary" />
                </v-card>
            </v-window-item>
        </v-window>
        <p class="text-caption text-medium-emphasis mt-4">
            Las ventas, devoluciones, caja e inventario se registran como operaciones históricas.
            Las correcciones deben realizarse mediante reversas o nuevos movimientos.
        </p>
    </div>
</template>
