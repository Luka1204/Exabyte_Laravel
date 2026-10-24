<script setup lang="ts">
import { ref, watch } from 'vue';
import { apiErrorMessage } from '../../../shared/api/errorMessage';
import { useAuthStore } from '../../identity/stores/authStore';
import { categoryService } from '../services/categoryService';
import type { Category } from '../types/category';

const auth = useAuthStore();
const categories = ref<Category[]>([]);
const name = ref('');
const description = ref('');
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const success = ref('');

async function loadCategories() {
    loading.value = true;
    error.value = '';

    try {
        const response = await categoryService.list();
        categories.value = response.data;
    } catch (exception: unknown) {
        error.value = apiErrorMessage(exception, 'No se pudieron cargar las categorías.');
    } finally {
        loading.value = false;
    }
}

async function createCategory() {
    saving.value = true;
    error.value = '';
    success.value = '';

    try {
        await categoryService.create({
            name: name.value.trim(),
            description: description.value.trim() || null,
        });

        name.value = '';
        description.value = '';
        success.value = 'Categoría creada correctamente.';

        await loadCategories();
    } catch (exception: unknown) {
        error.value = apiErrorMessage(exception, 'No se pudo crear la categoría.');
    } finally {
        saving.value = false;
    }
}

watch(() => auth.activeCompanyId, loadCategories, { immediate: true });
</script>

<template>
    <div class="page-container">
        <div class="page-heading">
            <div>
                <span class="eyebrow">Catálogo</span>
                <h1>Categorías</h1>
                <p>Organizá los productos de tu negocio por tipo.</p>
            </div>
            <v-btn
                variant="outlined"
                color="secondary"
                prepend-icon="mdi-refresh"
                rounded="lg"
                :loading="loading"
                @click="loadCategories"
            >
                Actualizar
            </v-btn>
        </div>

        <v-alert
            v-if="error"
            type="error"
            class="mb-5"
            closable
            rounded="lg"
        >
            {{ error }}
        </v-alert>

        <v-alert
            v-if="success"
            type="success"
            class="mb-5"
            closable
            rounded="lg"
        >
            {{ success }}
        </v-alert>

        <v-card class="surface-card pa-5 mb-6">
            <div class="d-flex align-center ga-3 mb-4">
                <v-avatar color="red-lighten-5" rounded="lg">
                    <v-icon color="primary" icon="mdi-shape-plus-outline" />
                </v-avatar>
                <div>
                    <div class="text-h6 font-weight-bold text-secondary">Nueva categoría</div>
                    <div class="text-body-2 text-medium-emphasis">
                        Creá una categoría para clasificar tus productos.
                    </div>
                </div>
            </div>

            <v-form @submit.prevent="createCategory">
                <v-row>
                    <v-col cols="12" md="5">
                        <v-text-field
                            v-model="name"
                            label="Nombre"
                            maxlength="120"
                            variant="outlined"
                            rounded="lg"
                            required
                        />
                    </v-col>
                    <v-col cols="12" md="5">
                        <v-text-field
                            v-model="description"
                            label="Descripción (opcional)"
                            maxlength="2000"
                            variant="outlined"
                            rounded="lg"
                        />
                    </v-col>
                    <v-col cols="12" md="2">
                        <v-btn
                            type="submit"
                            color="primary"
                            block
                            size="large"
                            rounded="lg"
                            :loading="saving"
                            :disabled="!name.trim()"
                        >
                            Guardar
                        </v-btn>
                    </v-col>
                </v-row>
            </v-form>
        </v-card>

        <v-card class="surface-card">
            <v-table>
                <thead>
                    <tr class="table-header">
                        <th>Nombre</th>
                        <th>Descripción</th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-if="!loading && categories.length === 0">
                        <td colspan="2" class="text-center py-10 text-medium-emphasis">
                            <v-icon icon="mdi-shape-outline" size="34" class="mb-2" />
                            <div>No hay categorías todavía.</div>
                        </td>
                    </tr>

                    <tr
                        v-for="category in categories"
                        :key="category.id"
                    >
                        <td class="font-weight-medium text-secondary">{{ category.name }}</td>
                        <td class="text-medium-emphasis">{{ category.description || '—' }}</td>
                    </tr>
                </tbody>
            </v-table>

            <v-progress-linear
                v-if="loading"
                indeterminate
            />
        </v-card>
    </div>
</template>