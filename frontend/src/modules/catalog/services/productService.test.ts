import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { http } from '../../../shared/api/http';
import { useAuthStore } from '../../identity/stores/authStore';
import { productService } from './productService';

vi.mock('../../../shared/api/http', () => ({
    http: {
        get: vi.fn(),
        post: vi.fn(),
    },
    setRefreshHandlers: vi.fn(),
}));

describe('product service tenant context', () => {
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

    it('scopes product requests to the selected company', async () => {
        vi.mocked(http.get).mockResolvedValue({ data: { data: [] } });
        vi.mocked(http.post).mockResolvedValue({ data: { data: {} } });

        await productService.list();
        await productService.create({
            name: 'Producto',
            sku: 'PROD-01',
            barcode: null,
            category_id: null,
            price: '100.00',
            tax_rate: '21.00',
        });

        expect(http.get).toHaveBeenCalledWith('/products', expect.objectContaining({
            params: { per_page: 100 },
            headers: expect.objectContaining({ 'X-Company-ID': '11' }),
        }));
        expect(http.post).toHaveBeenCalledWith(
            '/products',
            expect.objectContaining({ sku: 'PROD-01' }),
            expect.objectContaining({
                headers: expect.objectContaining({ 'X-Company-ID': '11' }),
            }),
        );
    });
});
