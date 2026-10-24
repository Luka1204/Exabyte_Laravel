<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { apiErrorMessage } from '../../../shared/api/errorMessage';
import { subscriptionService, type Plan } from '../services/subscriptionService';

const plans = ref<Plan[]>([]);
const loading = ref(true);
const error = ref('');

onMounted(async () => {
    try {
        plans.value = (await subscriptionService.plans()).slice(0, 3);
    } catch (exception: unknown) {
        error.value = apiErrorMessage(exception, 'No se pudieron cargar los planes.');
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <main class="public-page">
        <header class="public-header">
            <RouterLink class="brand-link" to="/welcome">
                <span class="brand-mark">EX</span>
                <span>Exabyte</span>
            </RouterLink>
            <nav class="d-flex align-center ga-3">
                <RouterLink to="/plans" class="public-nav-link">Planes</RouterLink>
                <RouterLink to="/login" class="public-nav-link">Iniciar sesión</RouterLink>
                <v-btn color="primary" rounded="lg" to="/register">Crear cuenta</v-btn>
            </nav>
        </header>

        <section class="landing-hero public-content">
            <div>
                <span class="eyebrow">Gestión comercial para crecer</span>
                <h1>Tu comercio en orden. Tus decisiones más claras.</h1>
                <p>
                    Vendé, controlá inventario y conciliá caja con una herramienta
                    simple y conectada para tu equipo.
                </p>
                <div class="d-flex flex-wrap ga-3 mt-7">
                    <v-btn color="primary" size="large" rounded="lg" to="/plans">
                        Conocer planes
                    </v-btn>
                    <v-btn variant="outlined" color="secondary" size="large" rounded="lg" to="/register">
                        Crear una cuenta
                    </v-btn>
                </div>
            </div>
            <div class="landing-visual" aria-hidden="true">
                <div class="landing-visual-card">
                    <span class="brand-mark">EX</span>
                    <div class="landing-visual-lines"><i /><i /><i /></div>
                    <div class="landing-visual-chart"><span /><span /><span /><span /></div>
                </div>
            </div>
        </section>

        <section class="public-content feature-grid">
            <article class="feature-card">
                <v-icon icon="mdi-point-of-sale" color="primary" size="30" />
                <h2>Punto de venta</h2>
                <p>Registrá ventas en efectivo y consultá tickets desde un único flujo.</p>
            </article>
            <article class="feature-card">
                <v-icon icon="mdi-package-variant-closed" color="primary" size="30" />
                <h2>Inventario</h2>
                <p>Seguí existencias y movimientos vinculados a las operaciones.</p>
            </article>
            <article class="feature-card">
                <v-icon icon="mdi-chart-box-outline" color="primary" size="30" />
                <h2>Reportes</h2>
                <p>Revisá ventas, existencias y conciliación de caja por período.</p>
            </article>
        </section>

        <section class="public-content landing-pricing">
            <div class="d-flex align-end justify-space-between flex-wrap ga-4 mb-6">
                <div>
                    <span class="eyebrow">Elegí tu alcance</span>
                    <h2 class="text-h4 font-weight-bold text-secondary">Planes disponibles</h2>
                </div>
                <v-btn variant="text" color="primary" to="/plans">Comparar todos los planes</v-btn>
            </div>
            <v-alert v-if="error" type="warning" rounded="lg">{{ error }}</v-alert>
            <v-progress-linear v-else-if="loading" indeterminate color="primary" />
            <div v-else class="plan-grid">
                <v-card v-for="plan in plans" :key="plan.id" class="surface-card">
                    <v-card-text class="pa-5">
                        <span class="eyebrow">{{ plan.code }}</span>
                        <h3 class="text-h6 font-weight-bold text-secondary">{{ plan.name }}</h3>
                        <p class="plan-description">{{ plan.description }}</p>
                        <v-btn color="primary" rounded="lg" block to="/plans">Ver precio y límites</v-btn>
                    </v-card-text>
                </v-card>
            </div>
        </section>

        <section class="public-content faq-section">
            <span class="eyebrow">Preguntas frecuentes</span>
            <h2 class="text-h4 font-weight-bold text-secondary mb-4">Antes de contratar</h2>
            <v-expansion-panels variant="accordion">
                <v-expansion-panel title="¿Cómo se confirma el pago?">
                    <v-expansion-panel-text>
                        Mercado Pago informa el estado al backend. El retorno del navegador
                        no activa una suscripción por sí solo.
                    </v-expansion-panel-text>
                </v-expansion-panel>
                <v-expansion-panel title="¿Puedo cancelar mi plan?">
                    <v-expansion-panel-text>
                        La política de cancelación configurada actualmente es inmediata;
                        el historial de contrato y pagos se conserva.
                    </v-expansion-panel-text>
                </v-expansion-panel>
                <v-expansion-panel title="¿Están disponibles los términos legales?">
                    <v-expansion-panel-text>
                        Las condiciones comerciales y la política de privacidad requieren
                        revisión legal antes de habilitar contrataciones comerciales.
                    </v-expansion-panel-text>
                </v-expansion-panel>
            </v-expansion-panels>
        </section>

        <footer class="public-footer">
            <span>Exabyte · Gestión comercial</span>
            <span>Condiciones comerciales y privacidad pendientes de revisión legal.</span>
        </footer>
    </main>
</template>
