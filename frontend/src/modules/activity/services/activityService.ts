import { http } from '../../../shared/api/http';
import { useAuthStore } from '../../identity/stores/authStore';

export interface CompanyActivityEvent {
    id: number;
    action: string;
    subject_type: string;
    subject_id: string | null;
    details: {
        method: string;
        status: number;
        fields: string;
        is_active?: boolean;
    } | null;
    actor: { id: number; name: string } | null;
    created_at: string;
}

export interface ActivityPage {
    data: CompanyActivityEvent[];
    current_page: number;
    last_page: number;
    total: number;
}

export const activityService = {
    async list(page = 1): Promise<ActivityPage> {
        const auth = useAuthStore();
        const response = await http.get<ActivityPage>('/activity', {
            params: { page, per_page: 25 },
            headers: auth.companyHeaders(),
        });

        return response.data;
    },
};
