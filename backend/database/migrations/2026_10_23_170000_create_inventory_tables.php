<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->timestamps();
            $table->unique(['company_id', 'product_id']);
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('type', 32);
            $table->decimal('quantity_delta', 14, 3);
            $table->decimal('balance_after', 14, 3);
            $table->string('reason', 500);
            $table->uuid('idempotency_key');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['company_id', 'idempotency_key']);
            $table->index(['company_id', 'product_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_balances');
    }
};
