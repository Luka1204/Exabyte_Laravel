<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_access_exemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->restrictOnDelete();
            $table->string('reason', 120);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        DB::table('companies')->orderBy('id')->chunkById(500, function ($companies): void {
            DB::table('subscription_access_exemptions')->insert(
                $companies->map(fn (object $company): array => [
                    'company_id' => $company->id,
                    'reason' => 'legacy_company_before_subscription_enforcement',
                    'expires_at' => null,
                    'created_at' => now(),
                ])->all()
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_access_exemptions');
    }
};
