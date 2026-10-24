<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { apiErrorMessage } from '../../../shared/api/errorMessage';
import { useAuthStore } from '../../identity/stores/authStore';
import { inventoryService, type InventoryStock } from '../../inventory/services/inventoryService';
import { salesService, type CashSession, type Sale } from '../services/salesService';
import {
    loadScannerSuffix,
    saveScannerSuffix,
    type ScannerSuffix,
} from '../utils/scannerPreferences';

interface CartLine {
    productId: number;
    quantity: number;
}

const stocks = ref<InventoryStock[]>([]);
const auth = useAuthStore();
const cart = ref<CartLine[]>([]);
const sales = ref<Sale[]>([]);
const cashSession = ref<CashSession | null>(null);
const loading = ref(false);
const saving = ref(false);
const cashSaving = ref(false);
const error = ref('');
const success = ref('');
const openingAmount = ref('0.00');
const countedCash = ref('');
const cashReceived = ref('');
const productSearch = ref('');
const scannerSuffix = ref<ScannerSuffix>('Enter');
const saleToVoid = ref<Sale | null>(null);
const saleToReturn = ref<Sale | null>(null);
const returnQuantities = ref<Record<number, string>>({});
const returnReason = ref('');
const returnError = ref('');
const returning = ref(false);
const voidReason = ref('');
const voidError = ref('');
const voiding = ref(false);

const cartItems = computed(() => cart.value.flatMap((line) => {
    const product = stocks.value.find((item) => item.product_id === line.productId);
    return product ? [{ ...line, product }] : [];
}));

function lineSubtotalCents(line: (typeof cartItems.value)[number]) {
    return Math.round(Number(line.product.price) * 100 * line.quantity);
}

function lineTaxCents(line: (typeof cartItems.value)[number]) {
    return Math.round(lineSubtotalCents(line) * Number(line.product.tax_rate) / 100);
}

function lineTotal(line: (typeof cartItems.value)[number]) {
    return (lineSubtotalCents(line) + lineTaxCents(line)) / 100;
}

const subtotal = computed(() => cartItems.value.reduce(
    (sum, line) => sum + lineSubtotalCents(line),
    0,
) / 100);

const taxTotal = computed(() => cartItems.value.reduce(
    (sum, line) => sum + lineTaxCents(line),
    0,
) / 100);

const total = computed(() => subtotal.value + taxTotal.value);
const changeDue = computed(() => Math.max(0, Number(cashReceived.value || 0) - total.value));
const availableProducts = computed(() => stocks.value.filter(
    (product) => product.is_active && Number(product.quantity) > 0,
));
const filteredProducts = computed(() => {
    const query = productSearch.value.trim().toLocaleLowerCase();
    if (!query) {
        return availableProducts.value;
    }

    return availableProducts.value.filter((product) => [
        product.name,
        product.sku,
        product.barcode ?? '',
    ].some((value) => value.toLocaleLowerCase().includes(query)));
});
const canVoidSales = computed(
    () => ['owner', 'manager'].includes(auth.activeCompany?.role ?? ''),
);

async function loadData() {
    loading.value = true;
    error.value = '';

    try {
        const [stockPage, session, salesPage] = await Promise.all([
            inventoryService.stocks(),
            salesService.currentCashSession(),
            salesService.list(),
        ]);
        stocks.value = stockPage.data;
        cashSession.value = session;
        sales.value = salesPage.data;
        if (session && !countedCash.value) {
            countedCash.value = session.expected_cash;
        }
    } catch (exception: unknown) {
        error.value = apiErrorMessage(exception, 'No se pudo cargar el punto de venta.');
    } finally {
        loading.value = false;
    }
}

function addToCart(product: InventoryStock): boolean {
    const existing = cart.value.find((line) => line.productId === product.product_id);
    const currentQuantity = existing?.quantity ?? 0;
    if (currentQuantity + 1 > Number(product.quantity)) {
        error.value = `No hay existencias suficientes de ${product.name}.`;
        return false;
    }

    error.value = '';
    if (existing) {
        existing.quantity += 1;
    } else {
        cart.value.push({ productId: product.product_id, quantity: 1 });
    }

    return true;
}

function addScannedProduct() {
    const barcodeOrSku = productSearch.value.trim().toLocaleLowerCase();
    if (!barcodeOrSku) {
        return;
    }

    const product = availableProducts.value.find(
        (item) => item.barcode?.toLocaleLowerCase() === barcodeOrSku
            || item.sku.toLocaleLowerCase() === barcodeOrSku,
    );
    if (!product) {
        error.value = 'No encontramos un producto disponible con ese SKU o código de barras.';
        return;
    }

    if (addToCart(product)) {
        productSearch.value = '';
    }
}

function remainingReturnQuantity(item: Sale['items'][number]) {
    const returned = item.returns?.reduce(
        (sum, saleReturn) => sum + Number(saleReturn.quantity),
        0,
    ) ?? 0;

    return Math.max(0, Number(item.quantity) - returned);
}

function openReturn(sale: Sale) {
    saleToReturn.value = sale;
    returnReason.value = '';
    returnError.value = '';
    returnQuantities.value = Object.fromEntries(
        sale.items.map((item) => [item.id, '']),
    );
}

async function submitReturn() {
    if (!saleToReturn.value || !returnReason.value.trim()) {
        return;
    }

    returnError.value = '';
    const items = saleToReturn.value.items.flatMap((item) => {
        const quantity = Number(returnQuantities.value[item.id] || 0);
        if (quantity > remainingReturnQuantity(item)) {
            returnError.value = `La cantidad de ${item.product_name} supera lo que queda por devolver.`;
            return [];
        }
        if (quantity > 0 && Math.abs(quantity * 1000 - Math.round(quantity * 1000)) > 0.000001) {
            returnError.value = 'Las cantidades se ingresan con un máximo de tres decimales.';
            return [];
        }
        return quantity > 0
            ? [{ sale_item_id: item.id, quantity: quantity.toFixed(3) }]
            : [];
    });
    if (returnError.value) {
        return;
    }
    if (items.length === 0) {
        returnError.value = 'Ingresá la cantidad a devolver de al menos un artículo.';
        return;
    }

    returning.value = true;
    error.value = '';
    success.value = '';
    try {
        const ticketNumber = saleToReturn.value.ticket_number;
        const result = await salesService.returnSale(saleToReturn.value.id, {
            items,
            reason: returnReason.value.trim(),
            idempotency_key: crypto.randomUUID(),
        });
        saleToReturn.value = null;
        returnReason.value = '';
        returnQuantities.value = {};
        success.value = `Devolución registrada para ${ticketNumber}. Reintegro: ${formatMoney(result.data.total)}.`;
        await loadData();
    } catch (exception: unknown) {
        returnError.value = apiErrorMessage(exception, 'No se pudo registrar la devolución.');
    } finally {
        returning.value = false;
    }
}

function onScannerKeydown(event: KeyboardEvent) {
    const triggerKey = scannerSuffix.value === 'Enter'
        ? 'Enter'
        : scannerSuffix.value === 'Tab'
            ? 'Tab'
            : null;

    if (
        triggerKey
        && event.key === triggerKey
        && !event.altKey
        && !event.ctrlKey
        && !event.metaKey
    ) {
        event.preventDefault();
        addScannedProduct();
    }
}

function updateScannerSuffix(value: ScannerSuffix | null) {
    if (!value || value === scannerSuffix.value) {
        return;
    }

    const previousSuffix = scannerSuffix.value;
    scannerSuffix.value = value;
    try {
        saveScannerSuffix(value);
    } catch {
        scannerSuffix.value = previousSuffix;
        error.value = 'No se pudo guardar la configuración del lector en este dispositivo.';
    }
}

function setQuantity(productId: number, value: string | number) {
    const quantity = Number(value);
    const line = cart.value.find((item) => item.productId === productId);
    const product = stocks.value.find((item) => item.product_id === productId);
    if (!line || !product || !Number.isFinite(quantity)) {
        return;
    }

    if (quantity <= 0) {
        cart.value = cart.value.filter((item) => item.productId !== productId);
        return;
    }

    const roundedQuantity = Math.round(quantity * 1000) / 1000;
    if (roundedQuantity === 0) {
        error.value = 'La cantidad mínima para vender es 0,001.';
        return;
    }

    if (roundedQuantity > Number(product.quantity)) {
        error.value = `La cantidad supera las existencias de ${product.name}.`;
        return;
    }

    error.value = '';
    line.quantity = roundedQuantity;
}

function formatMoney(value: number | string) {
    return new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS',
    }).format(Number(value));
}

async function openCash() {
    const amount = Number(openingAmount.value);
    if (!Number.isFinite(amount) || amount < 0) {
        error.value = 'Ingresá un monto inicial válido.';
        return;
    }

    cashSaving.value = true;
    error.value = '';
    try {
        cashSession.value = await salesService.openCashSession(amount.toFixed(2));
        countedCash.value = cashSession.value.expected_cash;
        success.value = 'La caja quedó abierta. Ya podés registrar ventas.';
    } catch (exception: unknown) {
        error.value = apiErrorMessage(exception, 'No se pudo abrir la caja.');
    } finally {
        cashSaving.value = false;
    }
}

async function closeCash() {
    if (!cashSession.value) {
        return;
    }

    const amount = Number(countedCash.value);
    if (!Number.isFinite(amount) || amount < 0) {
        error.value = 'Ingresá el efectivo contado para cerrar la caja.';
        return;
    }

    cashSaving.value = true;
    error.value = '';
    try {
        const closed = await salesService.closeCashSession(
            cashSession.value.id,
            amount.toFixed(2),
        );
        cashSession.value = null;
        success.value = `Caja cerrada. Diferencia informada: ${formatMoney(closed.difference ?? 0)}.`;
    } catch (exception: unknown) {
        error.value = apiErrorMessage(exception, 'No se pudo cerrar la caja.');
    } finally {
        cashSaving.value = false;
    }
}

async function checkout() {
    if (!cashSession.value) {
        error.value = 'Abrí una caja antes de confirmar ventas.';
        return;
    }
    if (cart.value.length === 0) {
        error.value = 'Agregá al menos un producto a la venta.';
        return;
    }

    const received = Number(cashReceived.value);
    if (!Number.isFinite(received) || received < total.value) {
        error.value = 'El efectivo recibido debe cubrir el total de la venta.';
        return;
    }

    saving.value = true;
    error.value = '';
    success.value = '';
    try {
        const result = await salesService.checkout({
            items: cart.value.map((line) => ({
                product_id: line.productId,
                quantity: line.quantity.toFixed(3),
            })),
            cash_received: received.toFixed(2),
            idempotency_key: crypto.randomUUID(),
        });
        success.value = `Venta ${result.data.ticket_number} confirmada. Cambio: ${formatMoney(result.data.change_due)}.`;
        cart.value = [];
        cashReceived.value = '';
        productSearch.value = '';
        await loadData();
    } catch (exception: unknown) {
        error.value = apiErrorMessage(exception, 'No se pudo confirmar la venta.');
    } finally {
        saving.value = false;
    }
}

async function voidSale() {
    if (!saleToVoid.value || !voidReason.value.trim()) {
        return;
    }

    voiding.value = true;
    voidError.value = '';
    success.value = '';
    try {
        const ticketNumber = saleToVoid.value.ticket_number;
        await salesService.voidSale(saleToVoid.value.id, voidReason.value.trim());
        saleToVoid.value = null;
        voidError.value = '';
        saleToReturn.value = null;
        voidReason.value = '';
        returnError.value = '';
        returnReason.value = '';
        returnQuantities.value = {};
        success.value = `La venta ${ticketNumber} fue anulada y se restituyeron el stock y el efectivo esperado.`;
        await loadData();
    } catch (exception: unknown) {
        voidError.value = apiErrorMessage(exception, 'No se pudo anular la venta.');
    } finally {
        voiding.value = false;
    }
}

watch(
    () => auth.activeCompanyId,
    () => {
        stocks.value = [];
        cart.value = [];
        sales.value = [];
        cashSession.value = null;
        cashReceived.value = '';
        countedCash.value = '';
        saleToVoid.value = null;
        voidReason.value = '';
        success.value = '';
        loadData();
    },
    { immediate: true },
);

onMounted(() => {
    try {
        scannerSuffix.value = loadScannerSuffix();
    } catch {
        error.value = 'No se pudo leer la configuración guardada del lector. Se usará Enter.';
    }
});
</script>

<template>
    <div class="page-container">
        <div class="page-heading">
            <div>
                <span class="eyebrow">Operaciones</span>
                <h1>Punto de venta</h1>
                <p>Registrá ventas y controlá el efectivo de la jornada.</p>
            </div>
            <v-btn
                variant="outlined"
                color="secondary"
                prepend-icon="mdi-refresh"
                rounded="lg"
                :loading="loading"
                @click="loadData"
            >
                Actualizar
            </v-btn>
        </div>

        <v-alert v-if="error" type="error" class="mb-5" rounded="lg" closable>
            {{ error }}
        </v-alert>
        <v-alert v-if="success" type="success" class="mb-5" rounded="lg" closable>
            {{ success }}
        </v-alert>

        <v-card class="surface-card mb-6">
            <v-card-text class="pa-5">
                <div class="d-flex align-center justify-space-between flex-wrap ga-4">
                    <div class="d-flex align-center ga-4">
                        <v-avatar
                            :color="cashSession ? 'success' : 'warning'"
                            variant="tonal"
                            size="52"
                            rounded="lg"
                        >
                            <v-icon :icon="cashSession ? 'mdi-cash-register' : 'mdi-cash-lock'" size="26" />
                        </v-avatar>
                        <div>
                            <div class="text-subtitle-1 font-weight-bold text-secondary">
                                {{ cashSession ? 'Caja abierta' : 'Caja cerrada' }}
                            </div>
                            <div class="text-body-2 text-medium-emphasis">
                                <template v-if="cashSession">
                                    Efectivo esperado: {{ formatMoney(cashSession.expected_cash) }}
                                </template>
                                <template v-else>
                                    Abrí la caja para empezar a vender.
                                </template>
                            </div>
                        </div>
                    </div>
                    <div v-if="cashSession" class="d-flex align-center ga-2 flex-wrap">
                        <v-text-field
                            v-model="countedCash"
                            label="Efectivo contado"
                            type="number"
                            min="0"
                            step="0.01"
                            prefix="$"
                            density="compact"
                            variant="outlined"
                            rounded="lg"
                            hide-details
                            style="width: 190px"
                        />
                        <v-btn
                            color="secondary"
                            variant="outlined"
                            rounded="lg"
                            :loading="cashSaving"
                            @click="closeCash"
                        >
                            Cerrar caja
                        </v-btn>
                    </div>
                    <div v-else class="d-flex align-center ga-2 flex-wrap">
                        <v-text-field
                            v-model="openingAmount"
                            label="Fondo inicial"
                            type="number"
                            min="0"
                            step="0.01"
                            prefix="$"
                            density="compact"
                            variant="outlined"
                            rounded="lg"
                            hide-details
                            style="width: 190px"
                        />
                        <v-btn
                            color="primary"
                            prepend-icon="mdi-lock-open-variant"
                            rounded="lg"
                            :loading="cashSaving"
                            @click="openCash"
                        >
                            Abrir caja
                        </v-btn>
                    </div>
                </div>
            </v-card-text>
        </v-card>

        <v-row align="start">
            <v-col cols="12" lg="7">
                <div class="d-flex align-center justify-space-between mb-4">
                    <div>
                        <h2 class="text-h6 font-weight-bold text-secondary">Productos disponibles</h2>
                        <div class="text-body-2 text-medium-emphasis">
                            {{ availableProducts.length }} con stock
                        </div>
                    </div>
                </div>

                <v-text-field
                    v-model="productSearch"
                    label="Buscar producto, SKU o código de barras"
                    prepend-inner-icon="mdi-magnify"
                    variant="outlined"
                    rounded="lg"
                    clearable
                    class="mb-2"
                    :disabled="!cashSession"
                    @keydown="onScannerKeydown"
                />
                <div class="d-flex align-center justify-space-between flex-wrap ga-3 mb-4">
                    <div class="d-flex align-center ga-2">
                        <v-icon icon="mdi-barcode-scan" color="info" size="small" />
                        <span class="text-caption text-medium-emphasis">
                            Lector configurado: {{ scannerSuffix === 'manual' ? 'ingreso manual' : scannerSuffix }}
                        </span>
                    </div>
                    <div class="d-flex align-center ga-2">
                        <v-select
                            :model-value="scannerSuffix"
                            :items="[
                                { title: 'Finalizar con Enter', value: 'Enter' },
                                { title: 'Finalizar con Tab', value: 'Tab' },
                                { title: 'Ingreso manual', value: 'manual' },
                            ]"
                            label="Sufijo del lector"
                            density="compact"
                            variant="outlined"
                            rounded="lg"
                            hide-details
                            :disabled="!cashSession"
                            style="min-width: 220px"
                            @update:model-value="updateScannerSuffix"
                        />
                        <v-btn
                            v-if="scannerSuffix === 'manual'"
                            color="primary"
                            variant="tonal"
                            rounded="lg"
                            :disabled="!productSearch.trim()"
                            @click="addScannedProduct"
                        >
                            Agregar
                        </v-btn>
                    </div>
                </div>

                <v-row>
                    <v-col
                        v-for="product in filteredProducts"
                        :key="product.product_id"
                        cols="12"
                        sm="6"
                        xl="4"
                    >
                        <v-card
                            class="surface-card h-100"
                            :disabled="!cashSession"
                            hover
                            @click="addToCart(product)"
                        >
                            <v-card-text class="pa-5">
                                <div class="d-flex align-start justify-space-between mb-5">
                                    <v-avatar color="blue-lighten-5" rounded="lg">
                                        <v-icon color="info" icon="mdi-package-variant" />
                                    </v-avatar>
                                    <v-chip size="small" color="success" variant="tonal">
                                        {{ Number(product.quantity).toLocaleString('es-AR', { maximumFractionDigits: 3 }) }} disp.
                                    </v-chip>
                                </div>
                                <div class="font-weight-bold text-secondary text-truncate">
                                    {{ product.name }}
                                </div>
                                <div class="text-caption text-medium-emphasis mb-3">SKU {{ product.sku }}</div>
                                <div class="text-h6 font-weight-bold text-primary">
                                    {{ formatMoney(product.price) }}
                                </div>
                            </v-card-text>
                        </v-card>
                    </v-col>
                    <v-col v-if="!loading && filteredProducts.length === 0" cols="12">
                        <v-card class="surface-card pa-8 text-center text-medium-emphasis">
                            {{ productSearch
                                ? 'No encontramos productos que coincidan con la búsqueda.'
                                : 'No hay productos activos con existencias. Revisá el catálogo y el inventario.' }}
                        </v-card>
                    </v-col>
                </v-row>
            </v-col>

            <v-col cols="12" lg="5">
                <v-card class="surface-card">
                    <v-card-text class="pa-5">
                        <div class="d-flex align-center justify-space-between mb-5">
                            <div>
                                <h2 class="text-h6 font-weight-bold text-secondary">Venta actual</h2>
                                <div class="text-body-2 text-medium-emphasis">
                                    {{ cartItems.length }} artículo{{ cartItems.length === 1 ? '' : 's' }}
                                </div>
                            </div>
                            <v-btn
                                v-if="cart.length"
                                variant="text"
                                color="error"
                                size="small"
                                @click="cart = []"
                            >
                                Vaciar
                            </v-btn>
                        </div>

                        <div v-if="cartItems.length === 0" class="py-8 text-center text-medium-emphasis">
                            <v-icon icon="mdi-cart-outline" size="38" class="mb-2" />
                            <div>Seleccioná productos para armar la venta.</div>
                        </div>
                        <div v-for="line in cartItems" :key="line.productId" class="d-flex align-center ga-3 py-3 border-b">
                            <div class="flex-grow-1 min-width-0">
                                <div class="font-weight-medium text-secondary text-truncate">
                                    {{ line.product.name }}
                                </div>
                                <div class="text-caption text-medium-emphasis">
                                    {{ formatMoney(line.product.price) }} c/u
                                </div>
                            </div>
                            <v-text-field
                                :model-value="line.quantity"
                                type="number"
                                min="0.001"
                                :max="Number(line.product.quantity)"
                                step="0.001"
                                density="compact"
                                variant="outlined"
                                rounded="lg"
                                hide-details
                                style="width: 92px"
                                @update:model-value="setQuantity(line.productId, $event)"
                            />
                            <div class="text-right font-weight-bold" style="min-width: 82px">
                                {{ formatMoney(lineTotal(line)) }}
                            </div>
                            <v-btn
                                icon="mdi-close"
                                variant="text"
                                size="small"
                                aria-label="Quitar producto"
                                @click="cart = cart.filter((item) => item.productId !== line.productId)"
                            />
                        </div>

                        <v-divider class="my-4" />
                        <div class="d-flex justify-space-between text-body-2 mb-2">
                            <span class="text-medium-emphasis">Subtotal</span>
                            <span>{{ formatMoney(subtotal) }}</span>
                        </div>
                        <div class="d-flex justify-space-between text-body-2 mb-4">
                            <span class="text-medium-emphasis">Impuestos estimados</span>
                            <span>{{ formatMoney(taxTotal) }}</span>
                        </div>
                        <div class="d-flex justify-space-between align-center mb-5">
                            <span class="text-subtitle-1 font-weight-bold text-secondary">Total</span>
                            <span class="text-h5 font-weight-bold text-secondary">{{ formatMoney(total) }}</span>
                        </div>
                        <v-text-field
                            v-model="cashReceived"
                            label="Efectivo recibido"
                            type="number"
                            min="0"
                            step="0.01"
                            prefix="$"
                            variant="outlined"
                            rounded="lg"
                            :disabled="!cashSession"
                        />
                        <div class="d-flex justify-space-between text-body-2 mb-4">
                            <span class="text-medium-emphasis">Cambio</span>
                            <span class="font-weight-bold text-success">{{ formatMoney(changeDue) }}</span>
                        </div>
                        <v-btn
                            block
                            color="primary"
                            size="large"
                            rounded="lg"
                            prepend-icon="mdi-check-circle-outline"
                            :loading="saving"
                            :disabled="!cashSession || cart.length === 0 || Number(cashReceived) < total"
                            @click="checkout"
                        >
                            Confirmar venta
                        </v-btn>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <section class="mt-8">
            <div class="mb-4">
                <h2 class="text-h6 font-weight-bold text-secondary">Ventas recientes</h2>
                <p class="text-body-2 text-medium-emphasis">Últimas operaciones de esta empresa.</p>
            </div>
            <v-card class="surface-card">
                <v-table>
                    <thead>
                        <tr class="table-header">
                            <th>Ticket</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th class="text-right">Total</th>
                            <th class="text-right">Cambio</th>
                            <th v-if="canVoidSales" class="text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!loading && sales.length === 0">
                            <td :colspan="canVoidSales ? 6 : 5" class="py-8 text-center text-medium-emphasis">
                                Todavía no hay ventas registradas.
                            </td>
                        </tr>
                        <tr v-for="sale in sales" :key="sale.id">
                            <td class="font-weight-medium text-secondary">{{ sale.ticket_number }}</td>
                            <td>{{ new Date(sale.created_at).toLocaleString('es-AR') }}</td>
                            <td>
                                <v-chip
                                    size="small"
                                    :color="sale.status === 'completed' ? 'success' : sale.status === 'partially_returned' ? 'warning' : sale.status === 'returned' ? 'info' : 'default'"
                                    variant="tonal"
                                >
                                    {{
                                        sale.status === 'completed' ? 'Completada'
                                            : sale.status === 'partially_returned' ? 'Devolución parcial'
                                                : sale.status === 'returned' ? 'Devuelta'
                                                    : sale.status === 'voided' ? 'Anulada' : sale.status
                                    }}
                                </v-chip>
                            </td>
                            <td class="text-right font-weight-bold">
                                {{ formatMoney(Number(sale.total) - (sale.returns?.reduce((sum, item) => sum + Number(item.total), 0) ?? 0)) }}
                            </td>
                            <td class="text-right">{{ formatMoney(sale.change_due) }}</td>
                            <td v-if="canVoidSales" class="text-right">
                                <v-btn
                                    v-if="sale.status === 'completed' && cashSession"
                                    color="error"
                                    variant="text"
                                    size="small"
                                    prepend-icon="mdi-cancel"
                                    @click="saleToVoid = sale; voidError = ''"
                                >
                                    Anular
                                </v-btn>
                                <v-btn
                                    v-if="['completed', 'partially_returned'].includes(sale.status) && cashSession"
                                    color="secondary"
                                    variant="text"
                                    size="small"
                                    prepend-icon="mdi-keyboard-return"
                                    @click="openReturn(sale)"
                                >
                                    Devolver
                                </v-btn>
                                <span v-else-if="sale.status === 'voided'" class="text-caption text-medium-emphasis">
                                    Anulada
                                </span>
                                <span v-else-if="sale.status === 'returned'" class="text-caption text-medium-emphasis">
                                    Devuelta
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </v-table>
            </v-card>
        </section>

        <v-dialog
            :model-value="saleToVoid !== null"
            max-width="480"
            @update:model-value="!$event && (saleToVoid = null)"
        >
            <v-card class="surface-card pa-2">
                <v-card-title class="text-h6 font-weight-bold text-secondary pa-5">
                    Anular venta
                </v-card-title>
                <v-card-text>
                    <v-alert v-if="voidError" type="error" class="mb-4" rounded="lg">
                        {{ voidError }}
                    </v-alert>
                    <v-alert type="warning" variant="tonal" class="mb-4" rounded="lg">
                        Se devolverá el stock y se descontará el importe del efectivo esperado de la caja abierta.
                    </v-alert>
                    <div class="mb-4 text-body-2">
                        Ticket <strong>{{ saleToVoid?.ticket_number }}</strong>
                        · {{ formatMoney(saleToVoid?.total ?? 0) }}
                    </div>
                    <v-textarea
                        v-model="voidReason"
                        label="Motivo de la anulación"
                        maxlength="500"
                        rows="3"
                        variant="outlined"
                        rounded="lg"
                        required
                    />
                    <div class="d-flex justify-end ga-3">
                        <v-btn
                            variant="text"
                            rounded="lg"
                            :disabled="voiding"
                            @click="saleToVoid = null"
                        >
                            Volver
                        </v-btn>
                        <v-btn
                            color="error"
                            rounded="lg"
                            :loading="voiding"
                            :disabled="!voidReason.trim()"
                            @click="voidSale"
                        >
                            Confirmar anulación
                        </v-btn>
                    </div>
                </v-card-text>
            </v-card>
        </v-dialog>

        <v-dialog
            :model-value="saleToReturn !== null"
            max-width="640"
            @update:model-value="!$event && (saleToReturn = null)"
        >
            <v-card class="surface-card pa-2">
                <v-card-title class="text-h6 font-weight-bold text-secondary pa-5">
                    Devolver artículos
                </v-card-title>
                <v-card-text>
                    <v-alert v-if="returnError" type="error" class="mb-4" rounded="lg">
                        {{ returnError }}
                    </v-alert>
                    <v-alert type="info" variant="tonal" class="mb-4" rounded="lg">
                        El reintegro se calcula con los importes originales y se descuenta del efectivo esperado de la caja abierta.
                    </v-alert>
                    <div class="mb-4 text-body-2">
                        Ticket <strong>{{ saleToReturn?.ticket_number }}</strong>
                    </div>
                    <div
                        v-for="item in saleToReturn?.items ?? []"
                        :key="item.id"
                        class="d-flex align-center ga-3 py-3 border-b"
                    >
                        <div class="flex-grow-1 min-width-0">
                            <div class="font-weight-medium text-secondary">{{ item.product_name }}</div>
                            <div class="text-caption text-medium-emphasis">
                                {{ item.sku }} · quedan {{ remainingReturnQuantity(item) }} de {{ item.quantity }}
                            </div>
                        </div>
                        <v-text-field
                            v-model="returnQuantities[item.id]"
                            label="Cantidad"
                            type="number"
                            min="0"
                            :max="remainingReturnQuantity(item)"
                            step="0.001"
                            density="compact"
                            variant="outlined"
                            rounded="lg"
                            hide-details
                            style="width: 140px"
                            :disabled="remainingReturnQuantity(item) === 0"
                        />
                    </div>
                    <v-textarea
                        v-model="returnReason"
                        label="Motivo de la devolución"
                        maxlength="500"
                        rows="2"
                        variant="outlined"
                        rounded="lg"
                        class="mt-5"
                        required
                    />
                    <div class="d-flex justify-end ga-3">
                        <v-btn
                            variant="text"
                            rounded="lg"
                            :disabled="returning"
                            @click="saleToReturn = null"
                        >
                            Cancelar
                        </v-btn>
                        <v-btn
                            color="primary"
                            rounded="lg"
                            :loading="returning"
                            :disabled="!returnReason.trim() || !Object.values(returnQuantities).some((value) => Number(value) > 0)"
                            @click="submitReturn"
                        >
                            Confirmar devolución
                        </v-btn>
                    </div>
                </v-card-text>
            </v-card>
        </v-dialog>
    </div>
</template>
