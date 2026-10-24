import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { http } from '../../../shared/api/http';
import { useAuthStore } from '../../identity/stores/authStore';
import { inventoryService } from './inventoryService';

vi.mock('../../../shared/api/http', () => ({
    http: {
        get: vi.fn(),
        post: vi.fn(),
    },
    setRefreshHandlers: vi.fn(),
}));

describe('inventory service tenant context', () => {
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

    it('loads stock and posts adjustments within the selected company', async () => {
        vi.mocked(http.get).mockResolvedValue({ data: { data: [] } });
        vi.mocked(http.post).mockResolvedValue({ data: { data: {} } });
        const adjustment = {
            product_id: 5,
            quantity_delta: '3.000',
            reason: 'Ingreso inicial',
            idempotency_key: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        };

        await inventoryService.stocks();
        await inventoryService.adjust(adjustment);

        expect(http.get).toHaveBeenCalledWith(
            '/inventory/stocks',
            expect.objectContaining({
                params: { per_page: 100 },
                headers: expect.objectContaining({ 'X-Company-ID': '11' }),
            }),
        );
        expect(http.post).toHaveBeenCalledWith(
            '/inventory/adjustments',
            adjustment,
            expect.objectContaining({
                headers: expect.objectContaining({ 'X-Company-ID': '11' }),
            }),
        );
    });

    it('loads the tenant-scoped movement ledger', async () => {
        vi.mocked(http.get).mockResolvedValue({ data: { data: [] } });

        await inventoryService.movements();

        expect(http.get).toHaveBeenCalledWith(
            '/inventory/movements',
            expect.objectContaining({
                params: { per_page: 25 },
                headers: expect.objectContaining({ 'X-Company-ID': '11' }),
            }),
        );
    });
});
