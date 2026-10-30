import { http } from '../../../shared/api/http';
import { useAuthStore, type AuthCompany } from '../../identity/stores/authStore';

interface CompanyResponse {
    data: AuthCompany;
}

interface CompaniesResponse {
    data: AuthCompany[];
}

export type CompanyMemberRole = 'owner' | 'manager' | 'member';
export type CompanyPermission = 'catalog.view' | 'catalog.manage' | 'inventory.view' | 'inventory.manage' | 'sales.operate' | 'sales.manage' | 'reports.view' | 'activity.view';

export interface CompanyMember {
    id: number;
    name: string;
    email: string;
    role: CompanyMemberRole;
    is_active: boolean;
    permissions: CompanyPermission[];
}

interface MembersResponse {
    data: CompanyMember[];
}

interface MemberResponse {
    data: CompanyMember;
    account_created?: boolean;
}

export const companyService = {
    async list(): Promise<AuthCompany[]> {
        const auth = useAuthStore();
        const response = await http.get<CompaniesResponse>(
            '/companies',
            { headers: auth.authHeaders() },
        );

        auth.setCompanies(response.data.data);
        return response.data.data;
    },

    async create(name: string): Promise<AuthCompany> {
        const auth = useAuthStore();
        const response = await http.post<CompanyResponse>(
            '/companies',
            { name },
            { headers: auth.authHeaders() },
        );

        const company = response.data.data;
        auth.setCompanies([...auth.companies, company]);
        auth.setActiveCompany(company.id);

        return company;
    },

    async update(
        companyId: number,
        data: { name?: string; is_active?: boolean },
    ): Promise<AuthCompany> {
        const auth = useAuthStore();
        const response = await http.patch<{
            data: { id: number; name: string; is_active: boolean };
        }>(`/companies/${companyId}`, data, { headers: auth.authHeaders() });
        const updated = response.data.data;
        const company = auth.companies.find((item) => item.id === companyId);
        if (company) {
            auth.setCompanies(auth.companies.map((item) => (
                item.id === companyId
                    ? { ...item, name: updated.name, is_active: updated.is_active }
                    : item
            )));
        }

        return { ...updated, role: company?.role ?? 'owner' };
    },

    async listMembers(companyId: number): Promise<CompanyMember[]> {
        const auth = useAuthStore();
        const response = await http.get<MembersResponse>(
            `/companies/${companyId}/members`,
            { headers: auth.authHeaders() },
        );

        return response.data.data;
    },

    async addMember(companyId: number, input: { name: string; email: string; password: string; password_confirmation: string; role: Exclude<CompanyMemberRole, 'owner'> }): Promise<{ member: CompanyMember; accountCreated: boolean }> {
        const auth = useAuthStore();
        const response = await http.post<MemberResponse>(
            `/companies/${companyId}/members`,
            input,
            { headers: auth.authHeaders() },
        );
        return { member: response.data.data, accountCreated: response.data.account_created ?? false };
    },

    async updateMemberRole(
        companyId: number,
        memberId: number,
        role: Exclude<CompanyMemberRole, 'owner'>,
    ): Promise<CompanyMember> {
        const auth = useAuthStore();
        const response = await http.patch<MemberResponse>(
            `/companies/${companyId}/members/${memberId}`,
            { role },
            { headers: auth.authHeaders() },
        );

        return response.data.data;
    },

    async updateMemberPermissions(companyId: number, memberId: number, permissions: CompanyPermission[]): Promise<CompanyMember> {
        const auth = useAuthStore();
        const response = await http.patch<MemberResponse>(
            `/companies/${companyId}/members/${memberId}`,
            { permissions },
            { headers: auth.authHeaders() },
        );
        return response.data.data;
    },

    async removeMember(companyId: number, memberId: number): Promise<void> {
        const auth = useAuthStore();
        await http.delete(
            `/companies/${companyId}/members/${memberId}`,
            { headers: auth.authHeaders() },
        );
    },

    async setMemberActive(
        companyId: number,
        memberId: number,
        isActive: boolean,
    ): Promise<CompanyMember> {
        const auth = useAuthStore();
        const response = await http.patch<MemberResponse>(
            `/companies/${companyId}/members/${memberId}`,
            { is_active: isActive },
            { headers: auth.authHeaders() },
        );

        return response.data.data;
    },
};
