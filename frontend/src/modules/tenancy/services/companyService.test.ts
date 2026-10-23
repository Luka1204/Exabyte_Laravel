import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { http } from '../../../shared/api/http';
import { useAuthStore } from '../../identity/stores/authStore';
import { companyService } from './companyService';

vi.mock('../../../shared/api/http', () => ({
    http: {
        get: vi.fn(),
        post: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
    },
    setRefreshHandlers: vi.fn(),
}));

describe('company member service', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
        useAuthStore().setSession('access-token', 'refresh-token', {
            id: 7,
            name: 'Owner',
            email: 'owner@example.test',
        });
    });

    it('loads members from the selected company endpoint', async () => {
        const members = [{
            id: 12,
            name: 'Miembro',
            email: 'member@example.test',
            role: 'member' as const,
        }];
        vi.mocked(http.get).mockResolvedValueOnce({ data: { data: members } });

        await expect(companyService.listMembers(4)).resolves.toEqual(members);
        expect(http.get).toHaveBeenCalledWith(
            '/companies/4/members',
            { headers: { Authorization: expect.any(String) } },
        );
    });

    it('updates a member role using the authenticated company endpoint', async () => {
        const member = {
            id: 12,
            name: 'Miembro',
            email: 'member@example.test',
            role: 'manager' as const,
        };
        vi.mocked(http.patch).mockResolvedValueOnce({ data: { data: member } });

        await expect(
            companyService.updateMemberRole(4, 12, 'manager'),
        ).resolves.toEqual(member);
        expect(http.patch).toHaveBeenCalledWith(
            '/companies/4/members/12',
            { role: 'manager' },
            { headers: { Authorization: expect.any(String) } },
        );
    });

    it('removes a member from the scoped company endpoint', async () => {
        vi.mocked(http.delete).mockResolvedValueOnce({ data: null });

        await companyService.removeMember(4, 12);

        expect(http.delete).toHaveBeenCalledWith(
            '/companies/4/members/12',
            { headers: { Authorization: expect.any(String) } },
        );
    });
});
