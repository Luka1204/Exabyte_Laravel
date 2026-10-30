<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { isAxiosError } from 'axios';
import {
    companyService,
    type CompanyMember,
    type CompanyMemberRole,
    type CompanyPermission,
} from '../services/companyService';
import { useAuthStore, type AuthCompany } from '../../identity/stores/authStore';
import { subscriptionService } from '../../subscriptions/services/subscriptionService';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();
const name = ref('');
const saving = ref(false);
const error = ref('');
const membersLoading = ref(false);
const members = ref<CompanyMember[]>([]);
const ownerCompanies = computed(() => auth.companies.filter((company) => company.role === 'owner'));
const selectedCompany = ref<number | null>(ownerCompanies.value[0]?.id ?? null);
const memberError = ref('');
const memberSuccess = ref('');
const savingMemberId = ref<number | null>(null);
const savingCompany = ref(false);
const companyDialog = ref(false);
const editingCompany = ref<AuthCompany | null>(null);
const companyName = ref('');
const companyError = ref('');
const newMemberEmail = ref('');
const newMemberName = ref('');
const newMemberPassword = ref('');
const newMemberPasswordConfirmation = ref('');
const newMemberRole = ref<Exclude<CompanyMemberRole, 'owner'>>('member');
const permissionOptions: Array<{ title: string; value: CompanyPermission }> = [
    { title: 'Ver catálogo', value: 'catalog.view' },
    { title: 'Administrar catálogo', value: 'catalog.manage' },
    { title: 'Ver inventario', value: 'inventory.view' },
    { title: 'Administrar inventario', value: 'inventory.manage' },
    { title: 'Operar ventas y caja', value: 'sales.operate' },
    { title: 'Anular ventas y gestionar devoluciones', value: 'sales.manage' },
    { title: 'Ver reportes', value: 'reports.view' },
    { title: 'Ver actividad', value: 'activity.view' },
];

async function createCompany() {
    saving.value = true;
    error.value = '';

    try {
        const company = await companyService.create(name.value.trim());
        name.value = '';
        const planId = Number(route.query.plan);
        const period = route.query.period === 'annual' ? 'annual' : 'monthly';
        if (Number.isSafeInteger(planId) && planId > 0) {
            try {
                const checkout = await subscriptionService.start(company.id, planId, period);
                if (checkout.checkout_url) {
                    window.location.assign(checkout.checkout_url);
                    return;
                }
            } catch (exception: unknown) {
                error.value = `La empresa se creó, pero no se pudo iniciar el checkout: ${
                    errorMessage(exception, 'Intentá nuevamente desde Planes.')
                }`;
                return;
            }
        }
        await router.push('/categories');
    } catch (exception: unknown) {
        if (isAxiosError<{ message?: string; errors?: Record<string, string[]> }>(exception)) {
            error.value = exception.response?.data.errors?.name?.[0]
                ?? exception.response?.data.message
                ?? 'No se pudo crear la empresa.';
        } else {
            error.value = 'No se pudo crear la empresa.';
        }
    } finally {
        saving.value = false;
    }
}

async function useCompany(companyId: number) {
    const company = auth.companies.find((item) => item.id === companyId);
    if (company?.is_active === false) {
        return;
    }

    auth.setActiveCompany(companyId);
    await router.push('/categories');
}

function editCompany(company: AuthCompany) {
    editingCompany.value = company;
    companyName.value = company.name;
    companyError.value = '';
    companyDialog.value = true;
}

async function saveCompany() {
    if (!editingCompany.value) {
        return;
    }

    savingCompany.value = true;
    companyError.value = '';
    try {
        await companyService.update(editingCompany.value.id, {
            name: companyName.value.trim(),
        });
        companyDialog.value = false;
    } catch (exception: unknown) {
        companyError.value = errorMessage(exception, 'No se pudo actualizar la empresa.');
    } finally {
        savingCompany.value = false;
    }
}

async function toggleCompany(company: AuthCompany) {
    savingCompany.value = true;
    error.value = '';
    try {
        await companyService.update(company.id, { is_active: company.is_active === false });
    } catch (exception: unknown) {
        error.value = errorMessage(exception, 'No se pudo cambiar el estado de la empresa.');
    } finally {
        savingCompany.value = false;
    }
}

async function loadMembers(companyId: number) {
    selectedCompany.value = companyId;
    members.value = [];
    memberError.value = '';
    memberSuccess.value = '';
    membersLoading.value = true;

    try {
        members.value = await companyService.listMembers(companyId);
    } catch (exception: unknown) {
        memberError.value = errorMessage(exception, 'No se pudieron cargar los miembros.');
    } finally {
        membersLoading.value = false;
    }
}

async function manageMembers(companyId: number) {
    await loadMembers(companyId);
    document.getElementById('company-members')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function updateMemberRole(member: CompanyMember, role: CompanyMemberRole) {
    if (selectedCompany.value === null || role === 'owner') {
        return;
    }

    savingMemberId.value = member.id;
    memberError.value = '';

    try {
        const updated = await companyService.updateMemberRole(
            selectedCompany.value,
            member.id,
            role,
        );
        Object.assign(member, updated);
    } catch (exception: unknown) {
        memberError.value = errorMessage(exception, 'No se pudo actualizar el rol.');
    } finally {
        savingMemberId.value = null;
    }
}

async function addMember() {
    if (selectedCompany.value === null || !newMemberEmail.value.trim()) return;
    savingMemberId.value = -1;
    memberError.value = '';
    try {
        const result = await companyService.addMember(selectedCompany.value, {
            name: newMemberName.value.trim(),
            email: newMemberEmail.value.trim(),
            password: newMemberPassword.value,
            password_confirmation: newMemberPasswordConfirmation.value,
            role: newMemberRole.value,
        });
        members.value.push(result.member);
        members.value.sort((a, b) => a.name.localeCompare(b.name));
        memberSuccess.value = result.accountCreated
            ? 'Cuenta creada y asociada a esta empresa. Compartí las credenciales iniciales con el usuario.'
            : 'La cuenta existente fue asociada a esta empresa; su contraseña no se modificó.';
        newMemberName.value = '';
        newMemberEmail.value = '';
        newMemberPassword.value = '';
        newMemberPasswordConfirmation.value = '';
        newMemberRole.value = 'member';
    } catch (exception: unknown) {
        memberError.value = errorMessage(exception, 'No se pudo agregar el usuario.');
    } finally {
        savingMemberId.value = null;
    }
}

async function toggleMember(member: CompanyMember) {
    if (selectedCompany.value === null || member.role === 'owner') {
        return;
    }

    savingMemberId.value = member.id;
    memberError.value = '';
    try {
        const updated = await companyService.setMemberActive(
            selectedCompany.value,
            member.id,
            !member.is_active,
        );
        Object.assign(member, updated);
    } catch (exception: unknown) {
        memberError.value = errorMessage(exception, 'No se pudo cambiar el estado del miembro.');
    } finally {
        savingMemberId.value = null;
    }
}

async function toggleMemberPermission(member: CompanyMember, permission: CompanyPermission, enabled: boolean | null) {
    if (selectedCompany.value === null || member.role === 'owner') return;
    savingMemberId.value = member.id;
    memberError.value = '';
    try {
        const permissions = new Set(member.permissions);
        if (enabled) permissions.add(permission);
        else permissions.delete(permission);
        const updated = await companyService.updateMemberPermissions(
            selectedCompany.value, member.id, [...permissions],
        );
        Object.assign(member, updated);
    } catch (exception: unknown) {
        memberError.value = errorMessage(exception, 'No se pudo actualizar el permiso.');
    } finally {
        savingMemberId.value = null;
    }
}

function errorMessage(exception: unknown, fallback: string): string {
    if (isAxiosError<{ message?: string; errors?: Record<string, string[]> }>(exception)) {
        return Object.values(exception.response?.data.errors ?? {})[0]?.[0]
            ?? exception.response?.data.message
            ?? fallback;
    }

    return fallback;
}

onMounted(() => {
    if (selectedCompany.value !== null) void loadMembers(selectedCompany.value);
});
</script>

<template>
    <div class="page-container">
        <div class="page-heading">
            <div>
                <span class="eyebrow">Organización</span>
                <h1>Empresas</h1>
                <p>Administrá tus espacios de trabajo y sus miembros.</p>
            </div>
        </div>

        <v-alert
            v-if="error"
            type="error"
            class="mb-5"
            rounded="lg"
        >
            {{ error }}
        </v-alert>

        <v-card v-if="auth.companies.length" class="surface-card mb-6">
            <v-card-title class="pa-5 text-secondary">Tus empresas</v-card-title>
            <v-divider />
            <v-list class="py-2">
                <v-list-item
                    v-for="company in auth.companies"
                    :key="company.id"
                    rounded="lg"
                    class="mx-3 my-1"
                >
                    <template #prepend>
                        <v-avatar color="blue-lighten-5" rounded="lg">
                            <v-icon color="info" icon="mdi-domain" />
                        </v-avatar>
                    </template>
                    <v-list-item-title class="font-weight-bold text-secondary">
                        {{ company.name }}
                    </v-list-item-title>
                    <v-list-item-subtitle>
                        Rol: {{ company.role }} · {{ company.is_active === false ? 'Inactiva' : 'Activa' }}
                    </v-list-item-subtitle>
                    <template #append>
                        <v-btn
                            v-if="company.role === 'owner'"
                            variant="outlined"
                            color="secondary"
                            rounded="lg"
                            class="mr-3"
                            @click="manageMembers(company.id)"
                        >
                            Administrar miembros
                        </v-btn>
                        <template v-if="company.role === 'owner'">
                            <v-btn
                                variant="outlined"
                                color="secondary"
                                rounded="lg"
                                class="mr-3"
                                :disabled="savingCompany"
                                @click="editCompany(company)"
                            >
                                Editar
                            </v-btn>
                            <v-btn
                                variant="outlined"
                                :color="company.is_active === false ? 'success' : 'error'"
                                rounded="lg"
                                class="mr-3"
                                :loading="savingCompany"
                                @click="toggleCompany(company)"
                            >
                                {{ company.is_active === false ? 'Reactivar' : 'Dar de baja' }}
                            </v-btn>
                        </template>
                        <v-btn
                            color="primary"
                            rounded="lg"
                            :disabled="auth.activeCompanyId === company.id || company.is_active === false"
                            @click="useCompany(company.id)"
                        >
                            {{ auth.activeCompanyId === company.id ? 'En uso' : 'Usar empresa' }}
                        </v-btn>
                    </template>
                </v-list-item>
            </v-list>
        </v-card>

        <v-dialog v-model="companyDialog" max-width="520">
            <v-card class="surface-card">
                <v-card-title class="pa-5 text-secondary">Editar empresa</v-card-title>
                <v-card-text>
                    <v-alert v-if="companyError" type="error" class="mb-4">
                        {{ companyError }}
                    </v-alert>
                    <v-text-field
                        v-model="companyName"
                        label="Nombre comercial"
                        maxlength="160"
                        variant="outlined"
                        rounded="lg"
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn @click="companyDialog = false">Cancelar</v-btn>
                    <v-btn
                        color="primary"
                        :loading="savingCompany"
                        :disabled="!companyName.trim()"
                        @click="saveCompany"
                    >
                        Guardar
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-card class="surface-card pa-5">
            <div class="d-flex align-center ga-3 mb-4">
                <v-avatar color="red-lighten-5" rounded="lg">
                    <v-icon color="primary" icon="mdi-domain-plus" />
                </v-avatar>
                <div>
                    <div class="text-h6 font-weight-bold text-secondary">Nueva empresa</div>
                    <div class="text-body-2 text-medium-emphasis">
                        Creá un nuevo espacio de trabajo para tu negocio.
                    </div>
                </div>
            </div>
            <v-form @submit.prevent="createCompany">
                <v-text-field
                    v-model="name"
                    label="Nombre comercial"
                    maxlength="160"
                    variant="outlined"
                    rounded="lg"
                    required
                />
                <v-btn
                    type="submit"
                    color="primary"
                    rounded="lg"
                    :loading="saving"
                    :disabled="!name.trim()"
                >
                    Crear empresa
                </v-btn>
            </v-form>
        </v-card>

        <v-card id="company-members" class="surface-card mt-6">
            <v-card-title class="pa-5 text-secondary">Gestión de usuarios, roles y permisos</v-card-title>
            <v-card-text>
                <v-select
                    :model-value="selectedCompany"
                    :items="ownerCompanies"
                    item-title="name"
                    item-value="id"
                    label="Empresa"
                    variant="outlined"
                    rounded="lg"
                    :disabled="ownerCompanies.length === 0"
                    @update:model-value="manageMembers"
                />
                <v-alert v-if="ownerCompanies.length === 0" type="info" variant="tonal">
                    Necesitás ser owner de una empresa para administrar sus usuarios.
                </v-alert>
                <template v-else>
                    <v-alert v-if="memberError" type="error" class="mb-4">{{ memberError }}</v-alert>
                    <v-alert v-if="memberSuccess" type="success" class="mb-4">{{ memberSuccess }}</v-alert>

                    <v-card variant="tonal" class="pa-4 mb-5">
                        <div class="text-subtitle-1 font-weight-bold mb-1">Crear o asociar usuario</div>
                        <div class="text-caption text-medium-emphasis mb-3">
                            Un correo nuevo crea una cuenta con contraseña inicial (12 caracteres mínimo, letras y números). Si el correo ya está registrado, se asocia esa cuenta sin modificar su contraseña. Cada correo corresponde a una sola cuenta global.
                        </div>
                        <v-row dense>
                            <v-col cols="12" md="4"><v-text-field v-model="newMemberName" label="Nombre" variant="outlined" density="compact" hide-details="auto" /></v-col>
                            <v-col cols="12" md="4"><v-text-field v-model="newMemberEmail" label="Correo" type="email" variant="outlined" density="compact" hide-details="auto" /></v-col>
                            <v-col cols="12" md="4"><v-select v-model="newMemberRole" :items="['member', 'manager']" label="Rol inicial" variant="outlined" density="compact" hide-details /></v-col>
                            <v-col cols="12" md="4"><v-text-field v-model="newMemberPassword" label="Contraseña inicial" type="password" variant="outlined" density="compact" hide-details="auto" /></v-col>
                            <v-col cols="12" md="4"><v-text-field v-model="newMemberPasswordConfirmation" label="Confirmar contraseña" type="password" variant="outlined" density="compact" hide-details="auto" /></v-col>
                            <v-col cols="12" md="4" class="d-flex align-center justify-end"><v-btn color="primary" :loading="savingMemberId === -1" :disabled="!newMemberEmail.trim() || savingMemberId !== null" @click="addMember">Crear / asociar usuario</v-btn></v-col>
                        </v-row>
                    </v-card>

                    <div class="d-flex align-center justify-space-between mb-2">
                        <div>
                            <div class="text-subtitle-1 font-weight-bold">Usuarios de {{ ownerCompanies.find((company) => company.id === selectedCompany)?.name }}</div>
                            <div class="text-caption text-medium-emphasis">Asigná el rol, los permisos y el estado de acceso.</div>
                        </div>
                        <v-btn icon="mdi-refresh" variant="text" aria-label="Actualizar usuarios" :loading="membersLoading" @click="selectedCompany && manageMembers(selectedCompany)" />
                    </div>
                    <v-data-table
                        :headers="[
                            { title: 'Nombre', key: 'name', minWidth: '140px' },
                            { title: 'Correo', key: 'email', minWidth: '180px' },
                            { title: 'Rol', key: 'role', width: '130px' },
                            { title: 'Permisos', key: 'permissions', minWidth: '350px', sortable: false },
                            { title: 'Estado', key: 'status', width: '130px', sortable: false },
                        ]"
                        :items="members"
                        :loading="membersLoading"
                        :items-per-page="10"
                        density="comfortable"
                        hover
                    >
                        <template #item.role="{ item }">
                            <span v-if="item.role === 'owner'">Owner</span>
                            <v-select
                                v-else
                                :model-value="item.role"
                                :items="['member', 'manager']"
                                density="compact"
                                hide-details
                                :disabled="savingMemberId !== null || !item.is_active"
                                @update:model-value="updateMemberRole(item, $event)"
                            />
                        </template>
                        <template #item.permissions="{ item }">
                            <div v-if="item.role !== 'owner'" class="d-flex flex-wrap ga-x-3 py-2">
                                <v-checkbox
                                    v-for="permission in permissionOptions"
                                    :key="permission.value"
                                    :model-value="item.permissions.includes(permission.value)"
                                    :label="permission.title"
                                    density="compact"
                                    hide-details
                                    :disabled="savingMemberId !== null || !item.is_active"
                                    @update:model-value="toggleMemberPermission(item, permission.value, $event)"
                                />
                            </div>
                            <span v-else class="text-medium-emphasis">Todos los permisos</span>
                        </template>
                        <template #item.status="{ item }">
                            <v-btn
                                v-if="item.role !== 'owner'"
                                :color="item.is_active ? 'error' : 'success'"
                                variant="text"
                                size="small"
                                :loading="savingMemberId === item.id"
                                :disabled="savingMemberId !== null"
                                @click="toggleMember(item)"
                            >{{ item.is_active ? 'Desactivar' : 'Reactivar' }}</v-btn>
                            <v-chip v-else color="success" variant="tonal" size="small">Activo</v-chip>
                        </template>
                        <template #no-data>
                            <div class="pa-6 text-center text-medium-emphasis">{{ membersLoading ? 'Cargando usuarios?' : 'Esta empresa todav?a no tiene usuarios.' }}</div>
                        </template>
                    </v-data-table>
                </template>
            </v-card-text>
        </v-card>
    </div>
</template>
