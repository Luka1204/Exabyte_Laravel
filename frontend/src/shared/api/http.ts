import axios from 'axios';
import type { AxiosError, InternalAxiosRequestConfig } from 'axios';

export const http = axios.create({
    baseURL: '/api/v1',
    headers:{
        Accept : 'application/json',
        'Content-Type':'application/json'
    }
})

type RefreshHandler = () => Promise<string>;
type ClearSessionHandler = () => void;

interface RetriableRequest extends InternalAxiosRequestConfig
{
    authRefreshRetried?: boolean;
}

let refreshHandler: RefreshHandler | null = null;
let clearSessionHandler: ClearSessionHandler | null = null;
let refreshInFlight: Promise<string> | null = null;

export function setRefreshHandlers(
    refresh: RefreshHandler | null,
    clearSession: ClearSessionHandler | null = null,
)
{
    refreshHandler = refresh;
    clearSessionHandler = clearSession;
}

http.interceptors.response.use(
    (response) => response,
    async (error: AxiosError) => {
        const request = error.config as RetriableRequest | undefined;
        const url = request?.url?.split('?')[0];
        const excludedPaths = ['/auth/login', '/auth/refresh', '/auth/logout'];

        if (error.response?.status !== 401 || !request) {
            return Promise.reject(error);
        }

        if (request.authRefreshRetried) {
            return Promise.reject(error);
        }

        if (
            !refreshHandler
            || !request.headers.get('Authorization')
            || excludedPaths.includes(url ?? '')
        ) {
            return Promise.reject(error);
        }

        request.authRefreshRetried = true;
        const currentRefresh = refreshInFlight ?? refreshHandler();
        refreshInFlight = currentRefresh;

        try {
            const accessToken = await currentRefresh;
            request.headers.set('Authorization', `Bearer ${accessToken}`);
            return await http.request(request);
        } catch (refreshError) {
            if (
                axios.isAxiosError(refreshError)
                && [401, 422].includes(refreshError.response?.status ?? 0)
            ) {
                clearSessionHandler?.();
            }
            return Promise.reject(refreshError);
        } finally {
            if (refreshInFlight === currentRefresh) {
                refreshInFlight = null;
            }
        }
    },
);