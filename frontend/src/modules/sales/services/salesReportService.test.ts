import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { http } from '../../../shared/api/http';
import { useAuthStore } from '../../identity/stores/authStore';
import { salesReportService } from './salesReportService';

vi.mock('../../../shared/api/http', () => ({
    http: {
        get: vi.fn(),
        post: vi.fn(),
    },
    setRefreshHandlers: vi.fn(),
}));

describe('sales report service tenant context', () => {
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

    it('requests the summary for a date range and selected company', async () => {
        const summary = {
            from: '2026-10-01',
            to: '2026-10-23',
            sales_count: 3,
            sales_total: '300.00',
            returns_count: 1,
            returns_total: '25.00',
            voided_count: 1,
            voided_total: '40.00',
            net_sales: '275.00',
            average_ticket: '100.00',
        };
        vi.mocked(http.get).mockResolvedValue({ data: { data: summary } });

        await expect(
            salesReportService.summary('2026-10-01', '2026-10-23'),
        ).resolves.toEqual(summary);
        expect(http.get).toHaveBeenCalledWith('/reports/sales', {
            params: { from: '2026-10-01', to: '2026-10-23' },
            headers: expect.objectContaining({
                Authorization: 'Bearer access-token',
                'X-Company-ID': '11',
            }),
        });
    });

    it('requests inventory and cash reports with tenant headers and date filters', async () => {
        vi.mocked(http.get)
            .mockResolvedValueOnce({ data: { data: { products_count: 4 } } })
            .mockResolvedValueOnce({ data: { data: { sessions_count: 2 } } });

        await salesReportService.inventory('2026-10-01', '2026-10-23');
        await salesReportService.cash('2026-10-01', '2026-10-23');

        expect(http.get).toHaveBeenNthCalledWith(1, '/reports/inventory', {
            params: { from: '2026-10-01', to: '2026-10-23' },
            headers: expect.objectContaining({
                Authorization: 'Bearer access-token',
                'X-Company-ID': '11',
            }),
        });
        expect(http.get).toHaveBeenNthCalledWith(2, '/reports/cash', {
            params: { from: '2026-10-01', to: '2026-10-23' },
            headers: expect.objectContaining({
                Authorization: 'Bearer access-token',
                'X-Company-ID': '11',
            }),
        });
    });
});
