<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_admins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('granted_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->json('benefits');
            $table->char('currency', 3)->default('ARS');
            $table->decimal('monthly_amount', 12, 2);
            $table->decimal('annual_amount', 12, 2);
            $table->json('limits');
            $table->json('entitlements');
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->unsignedSmallInteger('grace_days')->default(0);
            $table->unsignedInteger('terms_version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'id']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('billing_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->json('terms_snapshot');
            $table->string('billing_period', 16);
            $table->string('status', 24)->default('pending');
            $table->string('provider', 32)->default('mercadopago');
            $table->string('provider_status', 32)->nullable();
            $table->string('provider_subscription_id', 120)->nullable()->unique();
            $table->string('checkout_url', 2048)->nullable();
            $table->uuid('external_reference')->unique();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('last_provider_event_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status']);
            $table->index(['billing_user_id', 'status']);
        });

        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('provider', 32)->default('mercadopago');
            $table->string('kind', 24);
            $table->string('status', 24)->default('pending');
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->string('provider_invoice_id', 120)->nullable()->unique();
            $table->string('provider_payment_id', 120)->nullable()->unique();
            $table->string('provider_status', 32)->nullable();
            $table->string('external_reference', 120)->nullable();
            $table->json('provider_data')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['subscription_id', 'attempt_number']);
            $table->index(['subscription_id', 'status']);
        });

        Schema::create('provider_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('provider_event_id', 120);
            $table->string('topic', 64);
            $table->string('resource_id', 120);
            $table->string('request_id', 120)->nullable();
            $table->json('payload');
            $table->string('processing_status', 24)->default('received');
            $table->string('failure_code', 64)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_event_id']);
            $table->index(['processing_status', 'created_at']);
            $table->index(['provider', 'topic', 'resource_id']);
        });

        Schema::create('platform_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 100);
            $table->string('subject_type', 100);
            $table->string('subject_id', 120)->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
            $table->index(['actor_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audit_events');
        Schema::dropIfExists('provider_events');
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('platform_admins');
    }
};
