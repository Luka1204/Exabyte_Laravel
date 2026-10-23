<script setup lang="ts">
import { onMounted, ref } from 'vue';

import { categoryService } from '../services/categoryService';
import type { Category } from '../types/category';

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
    } catch {
        error.value = 'No se pudieron cargar las categorías.';
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
    } catch (exception: any) {
        const errors = exception.response?.data?.errors;
        error.value = errors?.name?.[0]
            ?? exception.response?.data?.message
            ?? 'No se pudo crear la categoría.';
    } finally {
        saving.value = false;
    }
}

onMounted(loadCategories);
</script>

<template>
    <v-container>
        <div class="d-flex align-center justify-space-between mb-6">
            <h1 class="text-h4">Categorías</h1>

            <v-btn
                prepend-icon="mdi-refresh"
                variant="outlined"
                @click="loadCategories"
            >
                Actualizar
            </v-btn>
        </div>

        <v-alert
            v-if="error"
            type="error"
            class="mb-4"
            closable
        >
            {{ error }}
        </v-alert>

        <v-alert
            v-if="success"
            type="success"
            class="mb-4"
            closable
        >
            {{ success }}
        </v-alert>

        <v-card class="pa-4 mb-6">
            <v-card-title>Nueva categoría</v-card-title>

            <v-form @submit.prevent="createCategory">
                <v-text-field
                    v-model="name"
                    label="Nombre"
                    maxlength="120"
                    required
                />

                <v-textarea
                    v-model="description"
                    label="Descripción"
                    maxlength="2000"
                    rows="2"
                />

                <v-btn
                    type="submit"
                    color="primary"
                    :loading="saving"
                >
                    Guardar categoría
                </v-btn>
            </v-form>
        </v-card>

        <v-card>
            <v-table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Descripción</th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-if="!loading && categories.length === 0">
                        <td colspan="2" class="text-center py-6">
                            No hay categorías registradas.
                        </td>
                    </tr>

                    <tr
                        v-for="category in categories"
                        :key="category.id"
                    >
                        <td>{{ category.name }}</td>
                        <td>{{ category.description || '—' }}</td>
                    </tr>
                </tbody>
            </v-table>

            <v-progress-linear
                v-if="loading"
                indeterminate
            />
        </v-card>
    </v-container>
</template>