import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { createRouter, createMemoryHistory } from 'vue-router';
import { defineComponent, h } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useAuthStore } from '../../identity/stores/authStore';
import { subscriptionService, type CompanySubscription, type Plan } from '../services/subscriptionService';
import CompanySubscriptionView from './CompanySubscriptionView.vue';
import PlansView from './PlansView.vue';

vi.mock('../../../shared/api/http', async (importOriginal) => {
    const actual = await importOriginal<typeof import('../../../shared/api/http')>();

    return {
        ...actual,
        http: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
    };
});

const testPlan: Plan = {
    id: 24,
    code: 'pro',
    name: 'Profesional',
    description: null,
    benefits: ['Reportes'],
    currency: 'ARS',
    monthly_amount: '15000.00',
    annual_amount: '150000.00',
    limits: { users: 8, branches: 2, cash_registers: 2, products: 1000 },
    entitlements: { sales_reports: true },
    trial_days: 0,
    grace_days: 5,
    terms_version: 2,
};

function currentSubscription(): CompanySubscription {
    return {
        id: 70,
        company_id: 12,
        plan_id: 4,
        billing_period: 'monthly',
        status: 'active',
        provider_status: 'authorized',
        checkout_url: null,
        terms_snapshot: {
            name: 'Comercio',
            amount: '9900.00',
            currency: 'ARS',
            limits: { users: 3, branches: 1, cash_registers: 1, products: 500 },
            entitlements: { pos: true },
        },
        current_period_end: null,
        canceled_at: null,
        payment_attempts: [],
    };
}

const passThroughStub = defineComponent({
    setup(_props, { slots }) {
        return () => h('div', slots.default?.());
    },
});

const buttonStub = defineComponent({
    props: { disabled: Boolean, loading: Boolean },
    emits: ['click'],
    setup(props, { attrs, slots, emit }) {
        return () => h('button', {
            ...attrs,
            disabled: props.disabled,
            onClick: () => emit('click'),
        }, slots.default?.());
    },
});

const periodToggleStub = defineComponent({
    props: { modelValue: String, disabled: Boolean },
    emits: ['update:modelValue'],
    setup(props, { attrs, emit }) {
        return () => h('select', {
            ...attrs,
            'data-testid': 'billing-period',
            value: props.modelValue,
            disabled: props.disabled,
            onChange: (event: Event) => emit('update:modelValue', (event.target as HTMLSelectElement).value),
        }, [
            h('option', { value: 'monthly' }, 'Mensual'),
            h('option', { value: 'annual' }, 'Anual'),
        ]);
    },
});

const planSelectStub = defineComponent({
    props: {
        modelValue: Number,
        items: { type: Array, default: () => [] },
        itemTitle: String,
        itemValue: String,
        disabled: Boolean,
    },
    emits: ['update:modelValue'],
    setup(props, { attrs, emit }) {
        return () => h('select', {
            ...attrs,
            'data-testid': 'plan-select',
            value: props.modelValue,
            disabled: props.disabled,
            onChange: (event: Event) => emit('update:modelValue', Number((event.target as HTMLSelectElement).value)),
        }, (props.items as Array<Record<string, unknown>>).map((item) => h('option', {
            value: item[props.itemValue ?? 'id'] as string | number,
        }, item[props.itemTitle ?? 'name'] as string)));
    },
});

const stubs = {
    VAlert: passThroughStub,
    VBtn: buttonStub,
    VBtnToggle: periodToggleStub,
    VCard: passThroughStub,
    VCardActions: passThroughStub,
    VCardText: passThroughStub,
    VCardTitle: passThroughStub,
    VChip: passThroughStub,
    VCol: passThroughStub,
    VDialog: passThroughStub,
    VDivider: passThroughStub,
    VIcon: passThroughStub,
    VList: passThroughStub,
    VListItem: passThroughStub,
    VProgressLinear: passThroughStub,
    VRow: passThroughStub,
    VSelect: planSelectStub,
    VTable: passThroughStub,
    RouterLink: defineComponent({
        setup(_props, { slots }) {
            return () => h('a', slots.default?.());
        },
    }),
};

async function mountView(component: typeof PlansView | typeof CompanySubscriptionView) {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/', component: { render: () => h('div') } },
            { path: '/subscription', component: { render: () => h('div') } },
        ],
    });
    await router.push('/');
    await router.isReady();

    return mount(component, {
        global: {
            plugins: [router],
            stubs,
        },
    });
}

function signInAsOwner() {
    const auth = useAuthStore();
    auth.setSession('access-token', 'refresh-token', {
        id: 8,
        name: 'Owner',
        email: 'owner@example.test',
    });
    auth.setCompanies([{ id: 12, name: 'Comercio', role: 'owner' }]);
}

describe('subscription plan-change views', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.restoreAllMocks();
        signInAsOwner();
    });

    it('lets an owner select an annual plan and shows success from the plans page', async () => {
        const original = currentSubscription();
        vi.spyOn(subscriptionService, 'plans').mockResolvedValue([testPlan]);
        vi.spyOn(subscriptionService, 'current').mockResolvedValue(original);
        const change = vi.spyOn(subscriptionService, 'change').mockResolvedValue({
            ...original,
            plan_id: testPlan.id,
            billing_period: 'annual',
        });
        const start = vi.spyOn(subscriptionService, 'start');
        const wrapper = await mountView(PlansView);
        await flushPromises();

        await wrapper.get('[data-testid="billing-period"]').setValue('annual');
        const action = wrapper.findAll('button').find((button) => button.text().includes('Cambiar a este plan'));
        expect(action).toBeDefined();
        await action!.trigger('click');
        await flushPromises();

        expect(change).toHaveBeenCalledWith(12, testPlan.id, 'annual');
        expect(start).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('El plan y período del contrato se actualizaron.');
    });

    it('shows provider errors on the plans page without starting a second checkout', async () => {
        const original = currentSubscription();
        vi.spyOn(subscriptionService, 'plans').mockResolvedValue([testPlan]);
        vi.spyOn(subscriptionService, 'current').mockResolvedValue(original);
        vi.spyOn(subscriptionService, 'change').mockRejectedValue(new Error('offline'));
        const start = vi.spyOn(subscriptionService, 'start');
        const wrapper = await mountView(PlansView);
        await flushPromises();

        const action = wrapper.findAll('button').find((button) => button.text().includes('Cambiar a este plan'));
        await action!.trigger('click');
        await flushPromises();

        expect(start).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('No se pudo actualizar el contrato.');
    });

    it('lets an owner choose a plan and period on the subscription page and shows success', async () => {
        const original = currentSubscription();
        vi.spyOn(subscriptionService, 'current').mockResolvedValue(original);
        vi.spyOn(subscriptionService, 'plans').mockResolvedValue([testPlan]);
        const change = vi.spyOn(subscriptionService, 'change').mockResolvedValue({
            ...original,
            plan_id: testPlan.id,
            billing_period: 'annual',
        });
        const wrapper = await mountView(CompanySubscriptionView);
        await flushPromises();

        await wrapper.get('[data-testid="plan-select"]').setValue(String(testPlan.id));
        await wrapper.get('[data-testid="billing-period"]').setValue('annual');
        const action = wrapper.findAll('button').find((button) => button.text().includes('Actualizar contrato'));
        await action!.trigger('click');
        await flushPromises();

        expect(change).toHaveBeenCalledWith(12, testPlan.id, 'annual');
        expect(wrapper.text()).toContain('El contrato se actualizó correctamente.');
    });

    it('shows provider errors on the subscription page and leaves its displayed contract unchanged', async () => {
        const original = currentSubscription();
        vi.spyOn(subscriptionService, 'current').mockResolvedValue(original);
        vi.spyOn(subscriptionService, 'plans').mockResolvedValue([testPlan]);
        vi.spyOn(subscriptionService, 'change').mockRejectedValue(new Error('offline'));
        const wrapper = await mountView(CompanySubscriptionView);
        await flushPromises();

        await wrapper.get('[data-testid="plan-select"]').setValue(String(testPlan.id));
        await wrapper.get('[data-testid="billing-period"]').setValue('annual');
        const action = wrapper.findAll('button').find((button) => button.text().includes('Actualizar contrato'));
        await action!.trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('No se pudo actualizar el contrato.');
        expect(wrapper.text()).toContain('ARS 9900.00');
        expect(wrapper.text()).toContain('Mensual');
    });
});
