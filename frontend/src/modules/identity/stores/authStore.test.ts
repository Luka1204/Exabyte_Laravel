import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useAuthStore } from './authStore';
import { http } from '../../../shared/api/http';

vi.mock('../../../shared/api/http', () => ({
    http: {
        post: vi.fn(),
    },
    setRefreshHandlers: vi.fn(),
}));

describe('auth store tenant selection', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.mocked(http.post).mockReset();
    });

    it('adds the selected company to authenticated request headers', () => {
        const auth = useAuthStore();
        auth.setSession('access-token', 'refresh-token', {
            id: 7,
            name: 'Usuario',
            email: 'usuario@example.test',
        });
        auth.setCompanies([
            { id: 11, name: 'Sucursal Norte', role: 'owner' },
            { id: 12, name: 'Sucursal Sur', role: 'member' },
        ]);
        auth.setActiveCompany(12);

        expect(auth.companyHeaders()).toEqual({
            Authorization: 'Bearer access-token',
            'X-Company-ID': '12',
        });
    });

    it('does not allow selecting a company outside the membership list', () => {
        const auth = useAuthStore();
        auth.setCompanies([{ id: 11, name: 'Empresa', role: 'owner' }]);

        expect(() => auth.setActiveCompany(99)).toThrow(
            'La empresa seleccionada no pertenece a la sesión.',
        );
    });

    it('clears tenant state when the session is cleared', () => {
        const auth = useAuthStore();
        auth.setSession('access-token', 'refresh-token', {
            id: 7,
            name: 'Usuario',
            email: 'usuario@example.test',
        });
        auth.setCompanies([{ id: 11, name: 'Empresa', role: 'owner' }]);

        auth.clearSession();

        expect(auth.activeCompany).toBeNull();
        expect(auth.companies).toEqual([]);
        expect(auth.refreshToken).toBeNull();
    });

    it('rotates the access and refresh token pair together', async () => {
        const auth = useAuthStore();
        auth.setSession('old-access', 'old-refresh', {
            id: 7,
            name: 'Usuario',
            email: 'usuario@example.test',
        });
        vi.mocked(http.post).mockResolvedValueOnce({
            data: {
                access_token: 'new-access',
                refresh_token: 'new-refresh',
            },
        } as never);

        await expect(auth.refreshAccessToken()).resolves.toBe('new-access');

        expect(auth.accessToken).toBe('new-access');
        expect(auth.refreshToken).toBe('new-refresh');
    });
});
