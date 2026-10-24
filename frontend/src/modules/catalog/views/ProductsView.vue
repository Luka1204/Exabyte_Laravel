<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { apiErrorMessage } from '../../../shared/api/errorMessage';
import { useAuthStore } from '../../identity/stores/authStore';
import { categoryService } from '../services/categoryService';
import { productService, type Product } from '../services/productService';
import type { Category } from '../types/category';

const products = ref<Product[]>([]);
const categories = ref<Category[]>([]);
const auth = useAuthStore();
const loading = ref(false);
const saving = ref(false);
const dialog = ref(false);
const error = ref('');
const success = ref('');
const form = ref({
    name: '',
    sku: '',
    barcode: '',
    category_id: null as number | null,
    price: '',
    tax_rate: '0',
});

const categoryNames = computed(
    () => new Map(categories.value.map((category) => [category.id, category.name])),
);

async function loadData() {
    loading.value = true;
    error.value = '';

    try {
        const [productPage, categoryPage] = await Promise.all([
            productService.list(),
            categoryService.list(100),
        ]);
        products.value = productPage.data;
        categories.value = categoryPage.data;
    } catch (exception: unknown) {
        error.value = apiErrorMessage(exception, 'No se pudieron cargar los productos.');
    } finally {
        loading.value = false;
    }
}

function openCreateDialog() {
    error.value = '';
    form.value = {
        name: '',
        sku: '',
        barcode: '',
        category_id: null,
        price: '',
        tax_rate: '0',
    };
    dialog.value = true;
}

async function createProduct() {
    if (!form.value.name.trim() || !form.value.sku.trim() || form.value.price === '') {
        return;
    }

    saving.value = true;
    error.value = '';
    success.value = '';

    try {
        await productService.create({
            name: form.value.name.trim(),
            sku: form.value.sku.trim(),
            barcode: form.value.barcode.trim() || null,
            category_id: form.value.category_id,
            price: Number(form.value.price).toFixed(2),
            tax_rate: Number(form.value.tax_rate || 0).toFixed(2),
        });
        dialog.value = false;
        success.value = 'Producto creado. Ahora podés ingresar su stock desde Inventario.';
        await loadData();
    } catch (exception: unknown) {
        error.value = apiErrorMessage(exception, 'No se pudo crear el producto.');
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

watch(() => auth.activeCompanyId, loadData, { immediate: true });
</script>

<template>
    <div class="page-container">
        <div class="page-heading">
            <div>
                <span class="eyebrow">Catálogo</span>
                <h1>Productos</h1>
                <p>Administrá el catálogo que vas a usar en el punto de venta.</p>
            </div>
            <div class="d-flex ga-3">
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
                <v-btn
                    color="primary"
                    prepend-icon="mdi-plus"
                    rounded="lg"
                    @click="openCreateDialog"
                >
                    Nuevo producto
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
                        <th>Categoría</th>
                        <th>Precio</th>
                        <th>Impuesto</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!loading && products.length === 0">
                        <td colspan="6" class="py-12 text-center text-medium-emphasis">
                            <v-icon icon="mdi-package-variant" size="34" class="mb-2" />
                            <div>Tu catálogo está vacío. Creá el primer producto para empezar.</div>
                        </td>
                    </tr>
                    <tr v-for="product in products" :key="product.id">
                        <td class="font-weight-medium text-secondary">{{ product.name }}</td>
                        <td>{{ product.sku }}</td>
                        <td>{{ categoryNames.get(product.category_id ?? -1) || 'Sin categoría' }}</td>
                        <td>{{ money(product.price) }}</td>
                        <td>{{ Number(product.tax_rate).toLocaleString('es-AR') }}%</td>
                        <td>
                            <v-chip
                                size="small"
                                :color="product.is_active ? 'success' : 'default'"
                                variant="tonal"
                            >
                                {{ product.is_active ? 'Activo' : 'Inactivo' }}
                            </v-chip>
                        </td>
                    </tr>
                </tbody>
            </v-table>
            <v-progress-linear v-if="loading" indeterminate color="primary" />
        </v-card>

        <v-dialog v-model="dialog" max-width="640">
            <v-card class="surface-card pa-2">
                <v-card-title class="text-h6 font-weight-bold text-secondary pa-5">
                    Crear producto
                </v-card-title>
                <v-card-text>
                    <v-alert v-if="error" type="error" class="mb-4" rounded="lg">
                        {{ error }}
                    </v-alert>
                    <v-form @submit.prevent="createProduct">
                        <v-text-field
                            v-model="form.name"
                            label="Nombre del producto"
                            maxlength="160"
                            variant="outlined"
                            rounded="lg"
                            required
                        />
                        <v-row>
                            <v-col cols="12" sm="6">
                                <v-text-field
                                    v-model="form.sku"
                                    label="SKU"
                                    maxlength="64"
                                    variant="outlined"
                                    rounded="lg"
                                    required
                                />
                            </v-col>
                            <v-col cols="12" sm="6">
                                <v-text-field
                                    v-model="form.barcode"
                                    label="Código de barras (opcional)"
                                    maxlength="64"
                                    variant="outlined"
                                    rounded="lg"
                                />
                            </v-col>
                            <v-col cols="12" sm="6">
                                <v-select
                                    v-model="form.category_id"
                                    :items="categories"
                                    item-title="name"
                                    item-value="id"
                                    label="Categoría"
                                    clearable
                                    variant="outlined"
                                    rounded="lg"
                                />
                            </v-col>
                            <v-col cols="12" sm="6">
                                <v-text-field
                                    v-model="form.price"
                                    label="Precio"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    prefix="$"
                                    variant="outlined"
                                    rounded="lg"
                                    required
                                />
                            </v-col>
                            <v-col cols="12" sm="6">
                                <v-text-field
                                    v-model="form.tax_rate"
                                    label="Impuesto"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    suffix="%"
                                    variant="outlined"
                                    rounded="lg"
                                />
                            </v-col>
                        </v-row>
                        <div class="d-flex justify-end ga-3">
                            <v-btn variant="text" rounded="lg" @click="dialog = false">
                                Cancelar
                            </v-btn>
                            <v-btn
                                type="submit"
                                color="primary"
                                rounded="lg"
                                :loading="saving"
                                :disabled="!form.name.trim() || !form.sku.trim() || form.price === ''"
                            >
                                Guardar producto
                            </v-btn>
                        </div>
                    </v-form>
                </v-card-text>
            </v-card>
        </v-dialog>
    </div>
</template>
