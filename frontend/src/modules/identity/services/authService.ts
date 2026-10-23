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
interface RegisterResponse
{
    require_verify: boolean;
    user:{
        id:number;
        name:string;
        email:string;
    }
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

    async register(email: string, password: string){
        const { data } = await http.post<RegisterResponse>(
            '/auth/register',
            {email, password}
        );
        return data.user;
    }
}