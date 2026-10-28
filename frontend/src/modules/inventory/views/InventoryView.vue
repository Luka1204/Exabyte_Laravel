<script setup lang="ts">
import { ref, watch } from 'vue';
import { apiErrorMessage } from '../../../shared/api/errorMessage';
import { useAuthStore } from '../../identity/stores/authStore';
import {
    inventoryService,
    type InventoryMovement,
    type InventoryStock,
} from '../services/inventoryService';

const stocks = ref<InventoryStock[]>([]);
const movements = ref<InventoryMovement[]>([]);
const auth = useAuthStore();
const loading = ref(false);
const saving = ref(false);
const dialog = ref(false);
const error = ref('');
const success = ref('');
const selectedProductId = ref<number | null>(null);
const quantity = ref('');
const reason = ref('');
const direction = ref<'in' | 'out'>('in');

async function loadStocks() {
    loading.value = true;
    error.value = '';

    try {
        const [stockPage, movementPage] = await Promise.all([
            inventoryService.stocks(),
            inventoryService.movements(),
        ]);
        stocks.value = stockPage.data;
        movements.value = movementPage.data;
    } catch (exception: unknown) {
        error.value = apiErrorMessage(exception, 'No se pudo cargar el inventario.');
    } finally {
        loading.value = false;
    }
}

function openAdjustment(product?: InventoryStock) {
    selectedProductId.value = product?.product_id ?? null;
    quantity.value = '';
    reason.value = '';
    direction.value = 'in';
    dialog.value = true;
}

async function saveAdjustment() {
    const amount = Math.round(Number(quantity.value) * 1000) / 1000;
    if (!selectedProductId.value || !Number.isFinite(amount) || amount <= 0 || !reason.value.trim()) {
        return;
    }

    saving.value = true;
    error.value = '';
    success.value = '';

    try {
        await inventoryService.adjust({
            product_id: selectedProductId.value,
            quantity_delta: (direction.value === 'in' ? amount : -amount).toFixed(3),
            reason: reason.value.trim(),
            idempotency_key: crypto.randomUUID(),
        });
        dialog.value = false;
        success.value = 'El ajuste de inventario se registró correctamente.';
        await loadStocks();
    } catch (exception: unknown) {
        error.value = apiErrorMessage(exception, 'No se pudo registrar el ajuste.');
    } finally {
        saving.value = false;
    }
}

function money(value: string) {
    return new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS',
    }).format(Number(value));
}

function movementLabel(type: InventoryMovement['type']) {
    return {
        adjustment: 'Ajuste',
        sale: 'Venta',
        sale_void: 'Anulación',
        sale_return: 'Devolución',
    }[type];
}

watch(() => auth.activeCompanyId, loadStocks, { immediate: true });
</script>

<template>
    <div class="page-container">
        <div class="page-heading">
            <div>
                <span class="eyebrow">Operaciones</span>
                <h1>Inventario</h1>
                <p>Consultá existencias y registrá ingresos o retiros de stock.</p>
            </div>
            <div class="d-flex ga-3">
                <v-btn
                    variant="outlined"
                    color="secondary"
                    prepend-icon="mdi-refresh"
                    rounded="lg"
                    :loading="loading"
                    @click="loadStocks"
                >
                    Actualizar
                </v-btn>
                <v-btn
                    color="primary"
                    prepend-icon="mdi-plus"
                    rounded="lg"
                    :disabled="stocks.length === 0"
                    @click="openAdjustment()"
                >
                    Ajustar stock
                </v-btn>
            </div>
        </div>

        <v-alert v-if="error" type="error" class="mb-5" rounded="lg" closable>
            {{ error }}
        </v-alert>
        <v-alert v-if="success" type="success" class="mb-5" rounded="lg" closable>
            {{ success }}
        </v-alert>

        <v-card class="surface-card">
            <v-table>
                <thead>
                    <tr class="table-header">
                        <th>Producto</th>
                        <th>SKU</th>
                        <th>Existencias</th>
                        <th>Precio</th>
                        <th class="text-right">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!loading && stocks.length === 0">
                        <td colspan="5" class="py-12 text-center text-medium-emphasis">
                            <v-icon icon="mdi-warehouse" size="34" class="mb-2" />
                            <div>No hay productos para mostrar. Primero creá productos en el catálogo.</div>
                        </td>
                    </tr>
                    <tr v-for="stock in stocks" :key="stock.product_id">
                        <td class="font-weight-medium text-secondary">{{ stock.name }}</td>
                        <td>{{ stock.sku }}</td>
                        <td>
                            <v-chip
                                size="small"
                                :color="Number(stock.quantity) > 0 ? 'success' : 'warning'"
                                variant="tonal"
                            >
                                {{ Number(stock.quantity).toLocaleString('es-AR', { maximumFractionDigits: 3 }) }}
                            </v-chip>
                        </td>
                        <td>{{ money(stock.price) }}</td>
                        <td class="text-right">
                            <v-btn
                                variant="text"
                                color="primary"
                                prepend-icon="mdi-swap-vertical"
                                @click="openAdjustment(stock)"
                            >
                                Ajustar
                            </v-btn>
                        </td>
                    </tr>
                </tbody>
            </v-table>
            <v-progress-linear v-if="loading" indeterminate color="primary" />
        </v-card>

        <section class="mt-8">
            <div class="mb-4">
                <h2 class="text-h6 font-weight-bold text-secondary">Movimientos recientes</h2>
                <p class="text-body-2 text-medium-emphasis">
                    Trazabilidad de ventas, anulaciones y ajustes de existencias.
                </p>
            </div>
            <v-card class="surface-card">
                <v-table>
                    <thead>
                        <tr class="table-header">
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th>Tipo</th>
                            <th>Motivo</th>
                            <th>Responsable</th>
                            <th class="text-right">Variación</th>
                            <th class="text-right">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!loading && movements.length === 0">
                            <td colspan="7" class="py-8 text-center text-medium-emphasis">
                                Todavía no hay movimientos de inventario.
                            </td>
                        </tr>
                        <tr v-for="movement in movements" :key="movement.id">
                            <td>{{ new Date(movement.created_at).toLocaleString('es-AR') }}</td>
                            <td>
                                <div class="font-weight-medium text-secondary">{{ movement.product.name }}</div>
                                <div class="text-caption text-medium-emphasis">{{ movement.product.sku }}</div>
                            </td>
                            <td>
                                <v-chip
                                    size="small"
                                    :color="movement.type === 'adjustment' ? 'info' : movement.type === 'sale' ? 'warning' : 'success'"
                                    variant="tonal"
                                >
                                    {{ movementLabel(movement.type) }}
                                </v-chip>
                            </td>
                            <td>{{ movement.reason }}</td>
                            <td>{{ movement.user.name }}</td>
                            <td
                                class="text-right font-weight-medium"
                                :class="Number(movement.quantity_delta) > 0 ? 'text-success' : 'text-error'"
                            >
                                {{ Number(movement.quantity_delta) > 0 ? '+' : '' }}{{ Number(movement.quantity_delta).toLocaleString('es-AR', { maximumFractionDigits: 3 }) }}
                            </td>
                            <td class="text-right">{{ Number(movement.balance_after).toLocaleString('es-AR', { maximumFractionDigits: 3 }) }}</td>
                        </tr>
                    </tbody>
                </v-table>
                <v-progress-linear v-if="loading" indeterminate color="primary" />
            </v-card>
        </section>

        <v-dialog v-model="dialog" max-width="520">
            <v-card class="surface-card pa-2">
                <v-card-title class="text-h6 font-weight-bold text-secondary pa-5">
                    Ajustar existencias
                </v-card-title>
                <v-card-text>
                    <v-alert v-if="error" type="error" class="mb-4" rounded="lg">
                        {{ error }}
                    </v-alert>
                    <v-form @submit.prevent="saveAdjustment">
                        <v-select
                            v-model="selectedProductId"
                            :items="stocks"
                            item-title="name"
                            item-value="product_id"
                            label="Producto"
                            variant="outlined"
                            rounded="lg"
                            required
                        />
                        <v-btn-toggle
                            v-model="direction"
                            mandatory
                            color="primary"
                            divided
                            class="mb-5 w-100"
                        >
                            <v-btn value="in" class="flex-grow-1">Ingreso</v-btn>
                            <v-btn value="out" class="flex-grow-1">Retiro</v-btn>
                        </v-btn-toggle>
                        <v-text-field
                            v-model="quantity"
                            label="Cantidad"
                            type="number"
                            min="0.001"
                            step="0.001"
                            variant="outlined"
                            rounded="lg"
                            required
                        />
                        <v-text-field
                            v-model="reason"
                            label="Motivo del ajuste"
                            maxlength="500"
                            variant="outlined"
                            rounded="lg"
                            required
                        />
                        <div class="d-flex justify-end ga-3">
                            <v-btn variant="text" rounded="lg" @click="dialog = false">
                                Cancelar
                            </v-btn>
                            <v-btn
                                type="submit"
                                color="primary"
                                rounded="lg"
                                :loading="saving"
                                :disabled="!selectedProductId || !Number(quantity) || !reason.trim()"
                            >
                                Registrar ajuste
                            </v-btn>
                        </div>
                    </v-form>
                </v-card-text>
            </v-card>
        </v-dialog>
    </div>
</template>
