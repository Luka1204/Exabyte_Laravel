<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { isAxiosError } from 'axios';
import {
    companyService,
    type CompanyMember,
    type CompanyMemberRole,
} from '../services/companyService';
import { useAuthStore } from '../../identity/stores/authStore';

const auth = useAuthStore();
const router = useRouter();
const name = ref('');
const saving = ref(false);
const error = ref('');
const memberDialog = ref(false);
const membersLoading = ref(false);
const members = ref<CompanyMember[]>([]);
const selectedCompany = ref<number | null>(null);
const memberError = ref('');
const savingMemberId = ref<number | null>(null);
const memberToRemove = ref<CompanyMember | null>(null);
const removingMember = ref(false);

async function createCompany() {
    saving.value = true;
    error.value = '';

    try {
        await companyService.create(name.value.trim());
        name.value = '';
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
    auth.setActiveCompany(companyId);
    await router.push('/categories');
}

async function manageMembers(companyId: number) {
    memberDialog.value = true;
    selectedCompany.value = companyId;
    members.value = [];
    memberError.value = '';
    membersLoading.value = true;

    try {
        members.value = await companyService.listMembers(companyId);
    } catch (exception: unknown) {
        memberError.value = errorMessage(exception, 'No se pudieron cargar los miembros.');
    } finally {
        membersLoading.value = false;
    }
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
        member.role = updated.role;
    } catch (exception: unknown) {
        memberError.value = errorMessage(exception, 'No se pudo actualizar el rol.');
    } finally {
        savingMemberId.value = null;
    }
}

async function removeMember() {
    if (selectedCompany.value === null || !memberToRemove.value) {
        return;
    }

    removingMember.value = true;
    memberError.value = '';

    try {
        await companyService.removeMember(
            selectedCompany.value,
            memberToRemove.value.id,
        );
        members.value = members.value.filter(
            (member) => member.id !== memberToRemove.value?.id,
        );
        memberToRemove.value = null;
    } catch (exception: unknown) {
        memberError.value = errorMessage(exception, 'No se pudo quitar al miembro.');
    } finally {
        removingMember.value = false;
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
</script>

<template>
    <v-container>
        <h1 class="text-h4 mb-6">Empresas</h1>

        <v-alert
            v-if="error"
            type="error"
            class="mb-4"
        >
            {{ error }}
        </v-alert>

        <v-card v-if="auth.companies.length" class="mb-6">
            <v-list>
                <v-list-item
                    v-for="company in auth.companies"
                    :key="company.id"
                    :title="company.name"
                    :subtitle="`Rol: ${company.role}`"
                >
                    <template #append>
                        <v-btn
                            v-if="company.role === 'owner'"
                            variant="text"
                            class="mr-2"
                            @click="manageMembers(company.id)"
                        >
                            Administrar miembros
                        </v-btn>
                        <v-btn
                            color="primary"
                            :disabled="auth.activeCompanyId === company.id"
                            @click="useCompany(company.id)"
                        >
                            {{ auth.activeCompanyId === company.id ? 'Seleccionada' : 'Usar empresa' }}
                        </v-btn>
                    </template>
                </v-list-item>
            </v-list>
        </v-card>

        <v-dialog v-model="memberDialog" max-width="760">
            <v-card>
                <v-card-title>Miembros de la empresa</v-card-title>
                <v-card-text>
                    <v-alert v-if="memberError" type="error" class="mb-4">
                        {{ memberError }}
                    </v-alert>

                    <v-progress-linear v-if="membersLoading" indeterminate />
                    <v-list v-else>
                        <v-list-item
                            v-for="member in members"
                            :key="member.id"
                            :title="member.name"
                            :subtitle="member.email"
                        >
                            <template #append>
                                <span v-if="member.role === 'owner'" class="mr-4">
                                    Owner
                                </span>
                                <template v-else>
                                    <v-select
                                        :model-value="member.role"
                                        :items="['member', 'manager']"
                                        label="Rol"
                                        density="compact"
                                        hide-details
                                        :loading="savingMemberId === member.id"
                                        :disabled="savingMemberId !== null"
                                        class="mr-3"
                                        style="max-width: 160px"
                                        @update:model-value="updateMemberRole(member, $event)"
                                    />
                                    <v-btn
                                        color="error"
                                        variant="text"
                                        :disabled="savingMemberId !== null"
                                        @click="memberToRemove = member"
                                    >
                                        Quitar
                                    </v-btn>
                                </template>
                            </template>
                        </v-list-item>
                    </v-list>
                    <p v-if="!membersLoading && members.length === 0" class="text-medium-emphasis">
                        No hay miembros para mostrar.
                    </p>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn @click="memberDialog = false">Cerrar</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog
            :model-value="memberToRemove !== null"
            max-width="440"
            @update:model-value="!$event && (memberToRemove = null)"
        >
            <v-card>
                <v-card-title>Quitar miembro</v-card-title>
                <v-card-text>
                    ¿Querés quitar a {{ memberToRemove?.name }} de esta empresa?
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn :disabled="removingMember" @click="memberToRemove = null">
                        Cancelar
                    </v-btn>
                    <v-btn
                        color="error"
                        :loading="removingMember"
                        @click="removeMember"
                    >
                        Quitar
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-card class="pa-4">
            <v-card-title>Nueva empresa</v-card-title>
            <v-form @submit.prevent="createCompany">
                <v-text-field
                    v-model="name"
                    label="Nombre comercial"
                    maxlength="160"
                    required
                />
                <v-btn
                    type="submit"
                    color="primary"
                    :loading="saving"
                    :disabled="!name.trim()"
                >
                    Crear empresa
                </v-btn>
            </v-form>
        </v-card>
    </v-container>
</template>
