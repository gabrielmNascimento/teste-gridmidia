<?php

namespace App\Jobs;

use App\Services\ConvertPendingExpense;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ConvertPendingExpenseJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 300, 900];

    public int $timeout = 30;

    public function __construct(
        public readonly int $expenseId,
    ) {}

    public function handle(ConvertPendingExpense $convertPendingExpense): void
    {
        $convertPendingExpense->handle($this->expenseId);
    }
}
