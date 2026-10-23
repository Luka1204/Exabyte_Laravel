import { AxiosError, type AxiosAdapter, type InternalAxiosRequestConfig } from 'axios';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { http, setRefreshHandlers } from './http';

describe('authenticated HTTP refresh', () => {
    const originalAdapter = http.defaults.adapter;

    beforeEach(() => {
        setRefreshHandlers(null);
    });

    afterEach(() => {
        http.defaults.adapter = originalAdapter;
        setRefreshHandlers(null);
    });

    it('refreshes once and retries an authenticated request', async () => {
        const refresh = vi.fn().mockResolvedValue('new-access');
        const adapter = vi.fn(async (config: InternalAxiosRequestConfig) => {
            if (config.headers.get('Authorization') === 'Bearer old-access') {
                const response = {
                    data: {},
                    status: 401,
                    statusText: 'Unauthorized',
                    headers: {},
                    config,
                };

                throw new AxiosError(
                    'Unauthorized',
                    'ERR_BAD_REQUEST',
                    config,
                    undefined,
                    response,
                );
            }

            return {
                data: { success: true },
                status: 200,
                statusText: 'OK',
                headers: {},
                config,
            };
        });
        http.defaults.adapter = adapter as AxiosAdapter;
        setRefreshHandlers(refresh, vi.fn());

        const response = await http.get('/protected', {
            headers: { Authorization: 'Bearer old-access' },
        });

        expect(response.data).toEqual({ success: true });
        expect(refresh).toHaveBeenCalledOnce();
        expect(adapter).toHaveBeenCalledTimes(2);
    });

    it('clears the session if the refreshed access token is rejected', async () => {
        const refresh = vi.fn().mockResolvedValue('still-invalid');
        const clearSession = vi.fn();
        const adapter = vi.fn(async (config: InternalAxiosRequestConfig) => {
            const response = {
                data: {},
                status: 401,
                statusText: 'Unauthorized',
                headers: {},
                config,
            };

            throw new AxiosError(
                'Unauthorized',
                'ERR_BAD_REQUEST',
                config,
                undefined,
                response,
            );
        });
        http.defaults.adapter = adapter as AxiosAdapter;
        setRefreshHandlers(refresh, clearSession);

        await expect(http.get('/protected', {
            headers: { Authorization: 'Bearer old-access' },
        })).rejects.toBeInstanceOf(AxiosError);

        expect(refresh).toHaveBeenCalledOnce();
        expect(clearSession).toHaveBeenCalledOnce();
    });
});
