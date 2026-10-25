import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { http } from '../../../shared/api/http';
import { useAuthStore } from '../../identity/stores/authStore';
import { subscriptionService } from './subscriptionService';

vi.mock('../../../shared/api/http', async (importOriginal) => {
    const actual = await importOriginal<typeof import('../../../shared/api/http')>();

    return {
        ...actual,
        http: {
            get: vi.fn(),
            post: vi.fn(),
            put: vi.fn(),
            delete: vi.fn(),
        },
    };
});

describe('subscription service', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
        const auth = useAuthStore();
        auth.setSession('access-token', 'refresh-token', {
            id: 7,
            name: 'Usuario',
            email: 'owner@example.test',
        });
        auth.setCompanies([{ id: 12, name: 'Comercio', role: 'owner' }]);
    });

    it('lists publicly available plans without requiring tenant context', async () => {
        vi.mocked(http.get).mockResolvedValue({ data: { data: [] } });

        await expect(subscriptionService.plans()).resolves.toEqual([]);
        expect(http.get).toHaveBeenCalledWith('/plans');
    });

    it('starts a server-priced plan checkout for the selected company', async () => {
        vi.mocked(http.post).mockResolvedValue({
            data: {
                data: { id: 44, status: 'pending' },
                checkout_url: 'https://mercadopago.example/checkout',
            },
        });

        await subscriptionService.start(12, 4, 'annual');

        const [url, payload, config] = vi.mocked(http.post).mock.calls[0]!;
        expect(url).toBe('/companies/12/subscription');
        expect(payload).toEqual({ plan_id: 4, billing_period: 'annual' });
        expect(config?.headers?.Authorization).toBeTruthy();
    });

    it('changes the existing subscription with the selected plan and billing period', async () => {
        vi.mocked(http.put).mockResolvedValue({
            data: { data: { id: 44, plan_id: 6, billing_period: 'annual', status: 'active' } },
        });

        await subscriptionService.change(12, 6, 'annual');

        const [url, payload, config] = vi.mocked(http.put).mock.calls[0]!;
        expect(url).toBe('/companies/12/subscription');
        expect(payload).toEqual({ plan_id: 6, billing_period: 'annual' });
        expect(config?.headers?.Authorization).toBeTruthy();
    });
});
