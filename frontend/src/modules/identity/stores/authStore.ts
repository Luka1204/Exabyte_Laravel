import { defineStore } from 'pinia';
import { ref } from 'vue';
import { http } from '../../../shared/api/http';

interface AuthUser
{
    id:number;
    name:string;
    email:string;
}

export const useAuthStore = defineStore('auth', ()=>{
    const accessToken = ref<string | null>(null);
    const user = ref<AuthUser | null>(null);

    function setSession(token: string, currentUser: AuthUser)
    {
        accessToken.value = token;
        user.value = currentUser;
    }

    function clearSession()
    {
        accessToken.value =null;
        user.value = null;
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
        user,
        setSession,
        clearSession,
        authHeaders,
        logout
    }
})