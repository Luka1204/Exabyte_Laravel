<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->foreignId('company_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();
            $table->unique(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'name']);
            $table->dropConstrainedForeignId('company_id');
            $table->unique('name');
        });
    }
};
