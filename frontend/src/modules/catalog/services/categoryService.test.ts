import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { http } from '../../../shared/api/http';
import { useAuthStore } from '../../identity/stores/authStore';
import { categoryService } from './categoryService';

vi.mock('../../../shared/api/http', () => ({
    http: {
        get: vi.fn(),
        post: vi.fn(),
    },
    setRefreshHandlers: vi.fn(),
}));

describe('category service tenant context', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();

        const auth = useAuthStore();
        auth.setSession('access-token', 'refresh-token', {
            id: 7,
            name: 'Usuario',
            email: 'usuario@example.test',
        });
        auth.setCompanies([{ id: 11, name: 'Empresa', role: 'owner' }]);
    });

    it('scopes category list requests to the selected company', async () => {
        vi.mocked(http.get).mockResolvedValue({
            data: { data: [], per_page: 15 },
        });

        await categoryService.list();

        expect(http.get).toHaveBeenCalledWith('/categories', {
            params: { per_page: 15 },
            headers: {
                Authorization: 'Bearer access-token',
                'X-Company-ID': '11',
            },
        });
    });

    it('does not send a client-supplied company id when creating a category', async () => {
        vi.mocked(http.post).mockResolvedValue({
            data: {
                data: {
                    id: 1,
                    company_id: 11,
                    name: 'Alimentos',
                    description: null,
                },
            },
        });

        await categoryService.create({
            name: 'Alimentos',
            description: null,
        });

        expect(http.post).toHaveBeenCalledWith(
            '/categories',
            { name: 'Alimentos', description: null },
            {
                headers: {
                    Authorization: 'Bearer access-token',
                    'X-Company-ID': '11',
                },
            },
        );
    });
});
