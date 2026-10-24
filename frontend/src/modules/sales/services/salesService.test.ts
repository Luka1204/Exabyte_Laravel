import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { http } from '../../../shared/api/http';
import { useAuthStore } from '../../identity/stores/authStore';
import { salesService } from './salesService';

vi.mock('../../../shared/api/http', () => ({
    http: {
        get: vi.fn(),
        post: vi.fn(),
    },
    setRefreshHandlers: vi.fn(),
}));

describe('sales service tenant context', () => {
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

    it('loads cash state and creates sales with tenant headers', async () => {
        vi.mocked(http.get).mockResolvedValue({ data: { data: null } });
        vi.mocked(http.post).mockResolvedValue({ data: { data: {} } });
        const payload = {
            items: [{ product_id: 5, quantity: '2.000' }],
            cash_received: '250.00',
            idempotency_key: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        };

        await salesService.currentCashSession();
        await salesService.checkout(payload);

        expect(http.get).toHaveBeenCalledWith(
            '/cash/session',
            expect.objectContaining({
                headers: expect.objectContaining({ 'X-Company-ID': '11' }),
            }),
        );
        expect(http.post).toHaveBeenCalledWith(
            '/sales',
            payload,
            expect.objectContaining({
                headers: expect.objectContaining({ 'X-Company-ID': '11' }),
            }),
        );
    });

    it('voids a sale with the tenant context and supplied reason', async () => {
        vi.mocked(http.post).mockResolvedValue({ data: { data: { id: 9 } } });

        await salesService.voidSale(9, 'Venta duplicada');

        expect(http.post).toHaveBeenCalledWith(
            '/sales/9/void',
            { reason: 'Venta duplicada' },
            expect.objectContaining({
                headers: expect.objectContaining({ 'X-Company-ID': '11' }),
            }),
        );
    });

    it('creates a return with the tenant context and idempotency key', async () => {
        vi.mocked(http.post).mockResolvedValue({
            data: { data: { id: 4, sale_id: 9, total: '121.00' }, replayed: false },
        });
        const payload = {
            items: [{ sale_item_id: 16, quantity: '1.000' }],
            reason: 'Producto sin usar',
            idempotency_key: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        };

        await salesService.returnSale(9, payload);

        expect(http.post).toHaveBeenCalledWith(
            '/sales/9/returns',
            payload,
            expect.objectContaining({
                headers: expect.objectContaining({ 'X-Company-ID': '11' }),
            }),
        );
    });
});
