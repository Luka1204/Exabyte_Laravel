import { defineStore } from 'pinia';
import { ref } from 'vue';
import { isAxiosError } from 'axios';
import { http, setRefreshHandlers } from '../../../shared/api/http';

interface AuthUser
{
    id:number;
    name:string;
    email:string;
}

export interface AuthCompany
{
    id:number;
    name:string;
    role:string;
    is_active?: boolean;
}

export const useAuthStore = defineStore('auth', ()=>{
    const accessToken = ref<string | null>(null);
    const refreshToken = ref<string | null>(null);
    const user = ref<AuthUser | null>(null);
    const companies = ref<AuthCompany[]>([]);
    const activeCompanyId = ref<number | null>(null);
    const activeCompany = ref<AuthCompany | null>(null);

    function setSession(token: string, newRefreshToken: string, currentUser: AuthUser)
    {
        accessToken.value = token;
        refreshToken.value = newRefreshToken;
        user.value = currentUser;
        setRefreshHandlers(refreshAccessToken, clearSession);
    }

    function clearSession()
    {
        setRefreshHandlers(null);
        accessToken.value = null;
        refreshToken.value = null;
        user.value = null;
        companies.value = [];
        activeCompanyId.value = null;
        activeCompany.value = null;
    }

    function authHeaders()
    {
        if(!accessToken.value)
        {
            throw new Error('No existe una sesión activa.');
        }

        return {
            Authorization: `Bearer ${accessToken.value}`
        }
    }

    async function refreshAccessToken(): Promise<string>
    {
        if (!refreshToken.value) {
            throw new Error('No existe un refresh token activo.');
        }

        try {
            const { data } = await http.post<{
                access_token: string;
                refresh_token: string;
            }>('/auth/refresh', {
                refresh_token: refreshToken.value,
            });

            accessToken.value = data.access_token;
            refreshToken.value = data.refresh_token;

            return data.access_token;
        } catch (error) {
            if (
                isAxiosError(error)
                && [401, 422].includes(error.response?.status ?? 0)
            ) {
                clearSession();
            }
            throw error;
        }
    }

    function setCompanies(availableCompanies: AuthCompany[])
    {
        companies.value = availableCompanies;
        const selected = availableCompanies.find(
            (company) => company.id === activeCompanyId.value,
        ) ?? availableCompanies[0] ?? null;

        activeCompany.value = selected;
        activeCompanyId.value = selected?.id ?? null;
    }

    function setActiveCompany(companyId: number)
    {
        const selected = companies.value.find(
            (company) => company.id === companyId,
        );

        if (!selected) {
            throw new Error('La empresa seleccionada no pertenece a la sesión.');
        }

        activeCompanyId.value = selected.id;
        activeCompany.value = selected;
    }

    function companyHeaders()
    {
        if (!activeCompanyId.value) {
            throw new Error('Seleccioná una empresa para continuar.');
        }

        return {
            ...authHeaders(),
            'X-Company-ID': String(activeCompanyId.value),
        };
    }

    async function logout()
    {
        try
        {
            if(accessToken.value){
                await http.post(
                    '/auth/logout',
                    {},
                    {headers:authHeaders()},
                )
            }
        }
        finally
        {
            clearSession();
        }
    }

    return {
        accessToken,
        refreshToken,
        user,
        companies,
        activeCompanyId,
        activeCompany,
        setSession,
        refreshAccessToken,
        clearSession,
        setCompanies,
        setActiveCompany,
        authHeaders,
        companyHeaders,
        logout
    }
})