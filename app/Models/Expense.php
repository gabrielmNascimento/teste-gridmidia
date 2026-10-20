<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'description',
    'supplier',
    'expense_date',
    'amount_cents',
    'currency',
    'converted_amount_cents',
    'exchange_rate',
    'exchange_rate_date',
    'exchange_rate_provider',
    'conversion_status',
])]
class Expense extends Model
{
    public const CURRENCY_BRL = 'BRL';

    public const CURRENCY_USD = 'USD';

    public const CONVERSION_NOT_REQUIRED = 'not_required';

    public const CONVERSION_PENDING = 'pending';

    public const CONVERSION_CONVERTED = 'converted';

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount_cents' => 'integer',
            'converted_amount_cents' => 'integer',
            'exchange_rate' => 'decimal:10',
            'exchange_rate_date' => 'date',
        ];
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ExpenseAllocation::class);
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'expense_allocations')
            ->withPivot(['percentage', 'amount_cents'])
            ->withTimestamps();
    }
}
