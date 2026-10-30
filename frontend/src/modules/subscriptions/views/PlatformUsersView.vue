<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { apiErrorMessage } from '../../../shared/api/errorMessage';
import { subscriptionService, type PlatformUser } from '../services/subscriptionService';

const users = ref<PlatformUser[]>([]);
const loading = ref(false);
const busyId = ref<number | null>(null);
const error = ref('');
const success = ref('');

async function load() {
    loading.value = true;
    error.value = '';
    try { users.value = await subscriptionService.platformUsers(); }
    catch (exception: unknown) { error.value = apiErrorMessage(exception, 'No se pudieron cargar los usuarios.'); }
    finally { loading.value = false; }
}

async function toggle(user: PlatformUser) {
    busyId.value = user.id;
    error.value = '';
    success.value = '';
    try {
        await subscriptionService.setPlatformAdmin(user.id, !user.is_platform_admin);
        user.is_platform_admin = !user.is_platform_admin;
        success.value = user.is_platform_admin
            ? `Se otorgó acceso de superadmin a ${user.email}.`
            : `Se revocó el acceso de superadmin a ${user.email}.`;
    } catch (exception: unknown) { error.value = apiErrorMessage(exception, 'No se pudo actualizar el acceso.'); }
    finally { busyId.value = null; }
}

onMounted(load);
</script>

<template>
    <div class="page-container">
        <div class="page-heading">
            <div><span class="eyebrow">Administración de plataforma</span><h1>Usuarios y superadmins</h1><p>Consultá las cuentas registradas y gestioná quién puede administrar la plataforma.</p></div>
            <div class="d-flex ga-2"><v-btn to="/platform" variant="tonal" color="secondary" rounded="lg">Resumen</v-btn><v-btn to="/platform/plans" variant="outlined" color="secondary" rounded="lg">Planes</v-btn><v-btn to="/platform/users" color="secondary" rounded="lg">Usuarios</v-btn></div>
        </div>
        <v-alert v-if="error" type="error" class="mb-4">{{ error }}</v-alert>
        <v-alert v-if="success" type="success" class="mb-4">{{ success }}</v-alert>
        <v-card class="surface-card">
            <v-progress-linear v-if="loading" indeterminate color="primary" />
            <v-table>
                <thead><tr><th>Usuario</th><th>Empresas</th><th>Registrado</th><th>Acceso de plataforma</th><th></th></tr></thead>
                <tbody>
                    <tr v-for="user in users" :key="user.id">
                        <td><strong>{{ user.name }}</strong><div class="text-caption text-medium-emphasis">{{ user.email }}</div></td>
                        <td>{{ user.companies_count }}</td>
                        <td>{{ new Date(user.created_at).toLocaleDateString('es-AR') }}</td>
                        <td><v-chip :color="user.is_platform_admin ? 'success' : 'default'" variant="tonal">{{ user.is_platform_admin ? 'Superadmin' : 'Usuario' }}</v-chip></td>
                        <td class="text-right"><v-btn :color="user.is_platform_admin ? 'error' : 'primary'" variant="text" :loading="busyId === user.id" :disabled="busyId !== null" @click="toggle(user)">{{ user.is_platform_admin ? 'Revocar' : 'Hacer superadmin' }}</v-btn></td>
                    </tr>
                    <tr v-if="!loading && users.length === 0"><td colspan="5" class="text-center text-medium-emphasis">No hay usuarios registrados.</td></tr>
                </tbody>
            </v-table>
        </v-card>
    </div>
</template>
