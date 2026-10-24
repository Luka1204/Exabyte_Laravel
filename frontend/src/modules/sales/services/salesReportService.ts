import { http } from '../../../shared/api/http';
import { useAuthStore } from '../../identity/stores/authStore';

export interface SalesSummary {
    from: string;
    to: string;
    sales_count: number;
    sales_total: string;
    returns_count: number;
    returns_total: string;
    voided_count: number;
    voided_total: string;
    net_sales: string;
    average_ticket: string;
}

export const salesReportService = {
    async summary(from: string, to: string) {
        const auth = useAuthStore();
        const response = await http.get<{ data: SalesSummary }>('/reports/sales', {
            params: { from, to },
            headers: auth.companyHeaders(),
        });

        return response.data.data;
    },
};
