import { http } from '../../../shared/api/http';
import { useAuthStore } from '../../identity/stores/authStore';

export interface Plan {
    id: number;
    code: string;
    name: string;
    description: string | null;
    benefits: string[];
    currency: string;
    monthly_amount: string;
    annual_amount: string;
    limits: {
        users: number;
        branches: number;
        cash_registers: number;
        products: number | null;
    };
    entitlements: Record<string, boolean>;
    trial_days: number;
    grace_days: number;
    terms_version: number;
    is_active?: boolean;
}

export interface PlanInput {
    code: string;
    name: string;
    description: string | null;
    benefits: string[];
    currency: 'ARS';
    monthly_amount: string;
    annual_amount: string;
    limits: Plan['limits'];
    entitlements: Record<string, boolean>;
    trial_days: number;
    grace_days: number;
    is_active: boolean;
}

export interface PlatformMetrics {
    registered_companies: number;
    companies_with_active_subscriptions: number;
    active_subscriptions: number;
    pending_subscriptions: number;
    canceled_subscriptions: number;
    failed_renewals: number;
    collected_revenue_ars: string;
    estimated_monthly_recurring_revenue_ars: string;
    plan_distribution: Array<{
        plan_id: number;
        plan_code: string;
        plan_name: string;
        status: string;
        subscriptions: number;
    }>;
    as_of: string;
}

export interface PlatformSubscription {
    id: number;
    status: string;
    billing_period: 'monthly' | 'annual';
    terms_snapshot: {
        amount: string;
        currency: string;
    };
    company: { id: number; name: string };
    billing_user: { id: number; name: string; email: string };
    plan: { id: number; code: string; name: string };
    payment_attempts: PaymentAttempt[];
}

export interface PlatformPayment extends PaymentAttempt {
    subscription: {
        id: number;
        billing_period: 'monthly' | 'annual';
        status: string;
        company: { id: number; name: string };
        plan: { id: number; code: string; name: string };
    };
}

export interface PaymentAttempt {
    id: number;
    kind: string;
    status: string;
    amount: string;
    currency: string;
    provider_status: string | null;
    paid_at: string | null;
}

export interface CompanySubscription {
    id: number;
    company_id: number;
    plan_id: number;
    billing_period: 'monthly' | 'annual';
    status: string;
    provider_status: string | null;
    checkout_url: string | null;
    terms_snapshot: {
        name: string;
        amount: string;
        currency: string;
        limits: Plan['limits'];
        entitlements: Record<string, boolean>;
    };
    current_period_end: string | null;
    canceled_at: string | null;
    payment_attempts: PaymentAttempt[];
}

export const subscriptionService = {
    async plans(): Promise<Plan[]> {
        const response = await http.get<{ data: Plan[] }>('/plans');

        return response.data.data;
    },

    async current(companyId: number): Promise<CompanySubscription | null> {
        const auth = useAuthStore();
        const response = await http.get<{ data: CompanySubscription | null }>(
            `/companies/${companyId}/subscription`,
            { headers: auth.authHeaders() },
        );

        return response.data.data;
    },

    async reconcile(companyId: number, preapprovalId: string): Promise<CompanySubscription> {
        const auth = useAuthStore();
        const response = await http.post<{ data: CompanySubscription }>(
            `/companies/${companyId}/subscription/reconcile`,
            { preapproval_id: preapprovalId },
            { headers: auth.authHeaders() },
        );

        return response.data.data;
    },

    async platformPlans(): Promise<Plan[]> {
        const auth = useAuthStore();
        const response = await http.get<{ data: Plan[] }>('/platform/plans', {
            headers: auth.authHeaders(),
        });

        return response.data.data;
    },

    async platformMetrics(): Promise<PlatformMetrics> {
        const auth = useAuthStore();
        const response = await http.get<{ data: PlatformMetrics }>('/platform/metrics', {
            headers: auth.authHeaders(),
        });

        return response.data.data;
    },

    async platformSubscriptions(): Promise<PlatformSubscription[]> {
        const auth = useAuthStore();
        const response = await http.get<{ data: PlatformSubscription[] }>('/platform/subscriptions', {
            headers: auth.authHeaders(),
        });

        return response.data.data;
    },

    async platformPayments(): Promise<PlatformPayment[]> {
        const auth = useAuthStore();
        const response = await http.get<{ data: PlatformPayment[] }>('/platform/payments', {
            headers: auth.authHeaders(),
        });

        return response.data.data;
    },

    async savePlan(input: PlanInput, planId?: number): Promise<Plan> {
        const auth = useAuthStore();
        const response = planId
            ? await http.put<{ data: Plan }>(`/platform/plans/${planId}`, input, {
                headers: auth.authHeaders(),
            })
            : await http.post<{ data: Plan }>('/platform/plans', input, {
                headers: auth.authHeaders(),
            });

        return response.data.data;
    },

    async start(companyId: number, planId: number, billingPeriod: 'monthly' | 'annual') {
        const auth = useAuthStore();
        const response = await http.post<{
            data: CompanySubscription;
            checkout_url: string | null;
        }>(
            `/companies/${companyId}/subscription`,
            { plan_id: planId, billing_period: billingPeriod },
            { headers: auth.authHeaders() },
        );

        return response.data;
    },

    async change(companyId: number, planId: number, billingPeriod: 'monthly' | 'annual') {
        const auth = useAuthStore();
        const response = await http.put<{ data: CompanySubscription }>(
            `/companies/${companyId}/subscription`,
            { plan_id: planId, billing_period: billingPeriod },
            { headers: auth.authHeaders() },
        );

        return response.data.data;
    },

    async cancel(companyId: number): Promise<CompanySubscription | null> {
        const auth = useAuthStore();
        const response = await http.delete<{ data: CompanySubscription | null }>(
            `/companies/${companyId}/subscription`,
            { headers: auth.authHeaders() },
        );

        return response.data.data;
    },
};
