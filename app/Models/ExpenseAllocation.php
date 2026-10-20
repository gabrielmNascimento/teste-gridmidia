<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['expense_id', 'unit_id', 'percentage', 'amount_cents'])]
class ExpenseAllocation extends Model
{
    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:4',
            'amount_cents' => 'integer',
        ];
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
