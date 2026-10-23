<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->timestamps();
        });

        Schema::create('company_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('user_id')
                ->constrained()
                ->restrictOnDelete();
            $table->string('role', 32);
            $table->timestamps();
            $table->unique(['company_id', 'user_id']);
            $table->index(['user_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_memberships');
        Schema::dropIfExists('companies');
    }
};
