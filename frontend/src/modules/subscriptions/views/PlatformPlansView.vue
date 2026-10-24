<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { apiErrorMessage } from '../../../shared/api/errorMessage';
import {
    subscriptionService,
    type Plan,
    type PlanInput,
} from '../services/subscriptionService';

const plans = ref<Plan[]>([]);
const loading = ref(false);
const saving = ref(false);
const dialog = ref(false);
const editingId = ref<number | undefined>();
const error = ref('');
const success = ref('');

const form = reactive({
    code: '',
    name: '',
    description: '',
    benefits: '',
    monthlyAmount: '',
    annualAmount: '',
    users: '1',
    branches: '1',
    cashRegisters: '1',
    products: '',
    graceDays: '0',
    isActive: true,
    entitlements: {
        catalog: true,
        pos: true,
        inventory: true,
        sales_reports: true,
        inventory_reports: true,
        cash_reports: true,
    } as Record<string, boolean>,
});
const entitlementKeys = Object.keys(form.entitlements);

function resetForm(plan?: Plan) {
    editingId.value = plan?.id;
    form.code = plan?.code ?? '';
    form.name = plan?.name ?? '';
    form.description = plan?.description ?? '';
    form.benefits = plan?.benefits.join('\n') ?? '';
    form.monthlyAmount = plan?.monthly_amount ?? '';
    form.annualAmount = plan?.annual_amount ?? '';
    form.users = String(plan?.limits.users ?? 1);
    form.branches = String(plan?.limits.branches ?? 1);
    form.cashRegisters = String(plan?.limits.cash_registers ?? 1);
    form.products = plan?.limits.products === null || plan?.limits.products === undefined
        ? ''
        : String(plan.limits.products);
    form.graceDays = String(plan?.grace_days ?? 0);
    form.isActive = plan?.is_active ?? true;
    form.entitlements = {
        catalog: true,
        pos: true,
        inventory: true,
        sales_reports: true,
        inventory_reports: true,
        cash_reports: true,
        ...(plan?.entitlements ?? {}),
    };
    error.value = '';
    dialog.value = true;
}

async function load() {
    loading.value = true;
    error.value = '';
    try {
        plans.value = await subscriptionService.platformPlans();
    } catch (exception: unknown) {
        error.value = apiErrorMessage(
            exception,
            'No se pudieron cargar los planes. Esta pantalla requiere permisos Superadmin.',
        );
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    error.value = '';
    success.value = '';
    try {
        const input: PlanInput = {
            code: form.code.trim(),
            name: form.name.trim(),
            description: form.description.trim() || null,
            benefits: form.benefits.split('\n').map((item) => item.trim()).filter(Boolean),
            currency: 'ARS',
            monthly_amount: form.monthlyAmount,
            annual_amount: form.annualAmount,
            limits: {
                users: Number(form.users),
                branches: Number(form.branches),
                cash_registers: Number(form.cashRegisters),
                products: form.products.trim() ? Number(form.products) : null,
            },
            entitlements: { ...form.entitlements },
            trial_days: 0,
            grace_days: Number(form.graceDays),
            is_active: form.isActive,
        };
        await subscriptionService.savePlan(input, editingId.value);
        success.value = editingId.value
            ? 'Plan actualizado. Los contratos existentes conservan sus condiciones.'
            : 'Plan creado y disponible para nuevos clientes.';
        dialog.value = false;
        await load();
    } catch (exception: unknown) {
        error.value = apiErrorMessage(exception, 'No se pudo guardar el plan.');
    } finally {
        saving.value = false;
    }
}

function amount(value: string, currency: string) {
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
                <h1>Planes comerciales</h1>
                <p>Los cambios aplican a nuevas contrataciones; cada contrato conserva su snapshot.</p>
            </div>
            <div class="d-flex ga-2">
                <v-btn to="/platform" variant="outlined" color="secondary" rounded="lg">Resumen</v-btn>
                <v-btn color="primary" prepend-icon="mdi-plus" rounded="lg" @click="resetForm()">
                    Crear plan
                </v-btn>
            </div>
        </div>

        <v-alert v-if="error" type="error" class="mb-5" rounded="lg">{{ error }}</v-alert>
        <v-alert v-if="success" type="success" class="mb-5" rounded="lg">{{ success }}</v-alert>
        <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-5" />

        <v-row>
            <v-col v-for="plan in plans" :key="plan.id" cols="12" md="6" xl="4">
                <v-card class="surface-card h-100">
                    <v-card-text class="pa-5">
                        <div class="d-flex justify-space-between align-start ga-3">
                            <div>
                                <span class="eyebrow">{{ plan.code }}</span>
                                <h2 class="text-h6 font-weight-bold text-secondary">{{ plan.name }}</h2>
                            </div>
                            <v-chip :color="plan.is_active ? 'success' : 'secondary'" variant="tonal">
                                {{ plan.is_active ? 'Activo' : 'Inactivo' }}
                            </v-chip>
                        </div>
                        <p class="plan-description">{{ plan.description }}</p>
                        <div class="my-4">
                            <div>{{ amount(plan.monthly_amount, plan.currency) }} / mes</div>
                            <div>{{ amount(plan.annual_amount, plan.currency) }} / año</div>
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            {{ plan.limits.users }} usuarios · {{ plan.limits.branches }} sucursales
                            · {{ plan.limits.cash_registers }} cajas
                        </div>
                        <div class="text-caption text-medium-emphasis mt-1">
                            Versión contractual {{ plan.terms_version }} · Gracia {{ plan.grace_days }} días
                        </div>
                        <v-btn
                            variant="outlined"
                            color="secondary"
                            rounded="lg"
                            class="mt-5"
                            @click="resetForm(plan)"
                        >
                            Editar oferta
                        </v-btn>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
        <v-alert v-if="!loading && plans.length === 0 && !error" type="info" rounded="lg">
            Todavía no hay planes. Creá el primero para publicarlo.
        </v-alert>

        <v-dialog v-model="dialog" max-width="760" scrollable>
            <v-card class="surface-card">
                <v-card-title class="pa-5 text-secondary">
                    {{ editingId ? 'Editar plan' : 'Crear plan' }}
                </v-card-title>
                <v-card-text>
                    <v-form @submit.prevent="save">
                        <v-row>
                            <v-col cols="12" sm="6">
                                <v-text-field v-model="form.code" label="Código (slug)" variant="outlined" />
                            </v-col>
                            <v-col cols="12" sm="6">
                                <v-text-field v-model="form.name" label="Nombre" variant="outlined" />
                            </v-col>
                            <v-col cols="12">
                                <v-textarea v-model="form.description" label="Descripción" rows="2" variant="outlined" />
                            </v-col>
                            <v-col cols="12">
                                <v-textarea
                                    v-model="form.benefits"
                                    label="Beneficios (uno por línea)"
                                    rows="3"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="12" sm="6">
                                <v-text-field v-model="form.monthlyAmount" label="Precio mensual (ARS)" type="number" min="0.01" step="0.01" variant="outlined" />
                            </v-col>
                            <v-col cols="12" sm="6">
                                <v-text-field v-model="form.annualAmount" label="Precio anual (ARS)" type="number" min="0.01" step="0.01" variant="outlined" />
                            </v-col>
                            <v-col cols="12" sm="3">
                                <v-text-field v-model="form.users" label="Usuarios" type="number" min="1" variant="outlined" />
                            </v-col>
                            <v-col cols="12" sm="3">
                                <v-text-field v-model="form.branches" label="Sucursales" type="number" min="1" variant="outlined" />
                            </v-col>
                            <v-col cols="12" sm="3">
                                <v-text-field v-model="form.cashRegisters" label="Cajas" type="number" min="1" variant="outlined" />
                            </v-col>
                            <v-col cols="12" sm="3">
                                <v-text-field v-model="form.products" label="Productos (vacío = sin límite)" type="number" min="1" variant="outlined" />
                            </v-col>
                            <v-col cols="12" sm="6">
                                <v-text-field v-model="form.graceDays" label="Días de gracia" type="number" min="0" max="90" variant="outlined" />
                            </v-col>
                            <v-col cols="12">
                                <div class="text-subtitle-2 font-weight-bold mb-2">Funcionalidades</div>
                                <v-checkbox
                                    v-for="key in entitlementKeys"
                                    :key="key"
                                    v-model="form.entitlements[key]"
                                    :label="key"
                                    density="compact"
                                    hide-details
                                />
                            </v-col>
                            <v-col cols="12">
                                <v-switch v-model="form.isActive" label="Disponible para nuevas contrataciones" color="primary" />
                            </v-col>
                            <v-col cols="12" class="text-caption text-medium-emphasis">
                                Los períodos de prueba aún no están habilitados en la integración actual.
                            </v-col>
                        </v-row>
                    </v-form>
                </v-card-text>
                <v-card-actions class="pa-5 pt-0">
                    <v-spacer />
                    <v-btn variant="text" @click="dialog = false">Cerrar</v-btn>
                    <v-btn color="primary" :loading="saving" @click="save">Guardar plan</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>
