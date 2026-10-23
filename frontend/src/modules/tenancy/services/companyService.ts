import { http } from '../../../shared/api/http';
import { useAuthStore, type AuthCompany } from '../../identity/stores/authStore';

interface CompanyResponse {
    data: AuthCompany;
}

interface CompaniesResponse {
    data: AuthCompany[];
}

export type CompanyMemberRole = 'owner' | 'manager' | 'member';

export interface CompanyMember {
    id: number;
    name: string;
    email: string;
    role: CompanyMemberRole;
}

interface MembersResponse {
    data: CompanyMember[];
}

interface MemberResponse {
    data: CompanyMember;
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

    async listMembers(companyId: number): Promise<CompanyMember[]> {
        const auth = useAuthStore();
        const response = await http.get<MembersResponse>(
            `/companies/${companyId}/members`,
            { headers: auth.authHeaders() },
        );

        return response.data.data;
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

    async removeMember(companyId: number, memberId: number): Promise<void> {
        const auth = useAuthStore();
        await http.delete(
            `/companies/${companyId}/members/${memberId}`,
            { headers: auth.authHeaders() },
        );
    },
};
