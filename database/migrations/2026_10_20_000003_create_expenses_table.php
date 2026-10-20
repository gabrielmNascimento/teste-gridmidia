<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->string('description');
            $table->string('supplier');
            $table->date('expense_date');
            $table->unsignedBigInteger('amount_cents');
            $table->enum('currency', ['BRL', 'USD']);
            $table->unsignedBigInteger('converted_amount_cents')->nullable();
            $table->decimal('exchange_rate', 20, 10)->nullable();
            $table->date('exchange_rate_date')->nullable();
            $table->string('exchange_rate_provider')->nullable();
            $table->enum('conversion_status', ['not_required', 'pending', 'converted'])
                ->default('not_required');
            $table->timestamps();

            $table->index(['expense_date', 'conversion_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
