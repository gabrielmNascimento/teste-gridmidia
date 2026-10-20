<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('expense_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->decimal('percentage', 7, 4);
            $table->unsignedBigInteger('amount_cents')->nullable();
            $table->timestamps();

            $table->unique(['expense_id', 'unit_id']);
            $table->index(['unit_id', 'expense_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_allocations');
    }
};
