import { http } from '../../../shared/api/http';
import { useAuthStore } from '../stores/authStore';
import { companyService } from '../../tenancy/services/companyService';

interface LoginResponse 
{
    access_token:string;
    refresh_token:string;
    token_type:'Bearer';
    expires_in: number;
    refresh_expires_in: number;
    user:{
        id:number;
        name:string;
        email:string;
    };
}
interface RegisterResponse {
    access_token: string;
    refresh_token: string;
    expires_in: number;
    refresh_expires_in: number;
    user: {
        id: number;
        name: string;
        email: string;
    };
    company: {
        id: number;
        name: string;
        role: 'owner';
    };
}

export const authService = {
    async login(email: string, password: string){
        const { data } = await http.post<LoginResponse>(
            '/auth/login',
            {email, password}
        );

        const auth = useAuthStore();
        auth.setSession(data.access_token, data.refresh_token, data.user);

        try {
            await companyService.list();
        } catch (error) {
            auth.clearSession();
            throw error;
        }

        return data.user;
    },

    async register(registration: {
        name: string;
        email: string;
        password: string;
        password_confirmation: string;
        company_name: string;
    }) {
        const { data: responseData } = await http.post<RegisterResponse>(
            '/auth/register',
            registration,
        );

        const auth = useAuthStore();
        auth.setSession(responseData.access_token, responseData.refresh_token, responseData.user);
        auth.setCompanies([responseData.company]);

        return responseData;
    }
}