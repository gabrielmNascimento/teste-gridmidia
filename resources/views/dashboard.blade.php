<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Rateio de despesas</title>
        <style>
            :root { color-scheme: light; font-family: system-ui, sans-serif; color: #172033; background: #f3f5f8; }
            body { margin: 0; }
            main { max-width: 1080px; margin: 0 auto; padding: 32px 20px 64px; }
            h1, h2 { margin-top: 0; }
            section { margin: 24px 0; padding: 24px; background: white; border: 1px solid #dce2ea; border-radius: 12px; }
            form { display: grid; gap: 12px; }
            label { display: grid; gap: 5px; font-weight: 600; }
            input, select, button { box-sizing: border-box; min-height: 40px; padding: 8px 10px; font: inherit; border: 1px solid #aeb8c7; border-radius: 6px; }
            button { width: fit-content; color: white; background: #2357c6; border: 0; cursor: pointer; }
            table { width: 100%; border-collapse: collapse; }
            th, td { padding: 10px 8px; text-align: left; border-bottom: 1px solid #e4e8ef; }
            .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 24px; }
            .allocation { display: grid; grid-template-columns: 2fr 1fr auto; gap: 8px; align-items: end; }
            .muted { color: #586579; }
            .notice { padding: 12px 16px; background: #e6f6ec; border-radius: 6px; }
            .errors { padding: 12px 16px; color: #8f1d1d; background: #fff0f0; border-radius: 6px; }
            @media (max-width: 600px) { section { padding: 16px; overflow-x: auto; } }
        </style>
    </head>
    <body>
        <main>
            <h1>Rateio de despesas</h1>
            <p class="muted">Cadastre empresas, unidades e despesas compartilhadas.</p>
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <span>Conectado como {{ auth()->user()->name }}.</span>
                <button type="submit">Sair</button>
            </form>

            @if (session('status'))
                <p class="notice">{{ session('status') }}</p>
            @endif

            @if ($errors->any())
                <div class="errors">
                    <strong>Não foi possível salvar:</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid">
                <section>
                    <h2>Empresa</h2>
                    <form method="post" action="{{ route('companies.store') }}">
                        @csrf
                        <label>Nome
                            <input name="name" maxlength="255" value="{{ old('name') }}" required>
                        </label>
                        <button type="submit">Cadastrar empresa</button>
                    </form>
                </section>

                <section>
                    <h2>Unidade</h2>
                    <form method="post" action="{{ route('units.store') }}">
                        @csrf
                        <label>Empresa
                            <select name="company_id" required>
                                <option value="">Selecione</option>
                                @foreach ($companies as $company)
                                    <option value="{{ $company->id }}" @selected(old('company_id') == $company->id)>{{ $company->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Nome
                            <input name="name" maxlength="255" value="{{ old('name') }}" required>
                        </label>
                        <label>Código (usado no CSV)
                            <input name="code" maxlength="255" value="{{ old('code') }}" required>
                        </label>
                        <button type="submit">Cadastrar unidade</button>
                    </form>
                </section>
            </div>

            <section>
                <h2>Despesa</h2>
                @if ($units->isEmpty())
                    <p class="muted">Cadastre uma unidade antes de registrar despesas.</p>
                @else
                    <form method="post" action="{{ route('expenses.store') }}">
                        @csrf
                        <div class="grid">
                            <label>Descrição
                                <input name="description" maxlength="255" value="{{ old('description') }}" required>
                            </label>
                            <label>Fornecedor
                                <input name="supplier" maxlength="255" value="{{ old('supplier') }}" required>
                            </label>
                            <label>Data
                                <input type="date" name="expense_date" value="{{ old('expense_date', now()->toDateString()) }}" required>
                            </label>
                            <label>Valor
                                <input type="number" name="amount" min="0.01" step="0.01" value="{{ old('amount') }}" required>
                            </label>
                            <label>Moeda
                                <select name="currency" required>
                                    <option value="BRL" @selected(old('currency', 'BRL') === 'BRL')>BRL</option>
                                    <option value="USD" @selected(old('currency') === 'USD')>USD</option>
                                </select>
                            </label>
                        </div>

                        <div>
                            <h3>Rateio</h3>
                            <div id="allocations">
                                @foreach (old('allocations', [['unit_id' => '', 'percentage' => '']]) as $index => $allocation)
                                    <div class="allocation">
                                        <label>Unidade
                                            <select name="allocations[{{ $index }}][unit_id]" required>
                                                <option value="">Selecione</option>
                                                @foreach ($units as $unit)
                                                    <option value="{{ $unit->id }}" @selected(($allocation['unit_id'] ?? '') == $unit->id)>{{ $unit->name }} — {{ $unit->company->name }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label>Percentual
                                            <input type="number" name="allocations[{{ $index }}][percentage]" min="0.0001" max="100" step="0.0001" value="{{ $allocation['percentage'] ?? '' }}" required>
                                        </label>
                                        <button type="button" class="remove-allocation" aria-label="Remover unidade do rateio">Remover</button>
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" id="add-allocation">Adicionar unidade ao rateio</button>
                        </div>
                        <button type="submit">Cadastrar despesa</button>
                    </form>
                @endif
            </section>

            <section>
                <h2>Importar despesas por CSV</h2>
                <p class="muted">Cada linha válida é importada independentemente. Use o formato documentado abaixo; rateios devem seguir <code>codigo=percentual;codigo=percentual</code>.</p>
                <form method="post" action="{{ route('expenses.import') }}" enctype="multipart/form-data">
                    @csrf
                    <label>Arquivo CSV
                        <input type="file" name="file" accept=".csv,text/csv" required>
                    </label>
                    <button type="submit">Importar arquivo</button>
                </form>
                @if ($importResults !== [])
                    <h3>Resultado por linha</h3>
                    <ul>
                        @foreach ($importResults as $result)
                            <li>
                                Linha {{ $result['line'] }}:
                                @if ($result['status'] === 'imported')
                                    importada (despesa #{{ $result['expense_id'] }})
                                @else
                                    {{ implode(' ', $result['errors']) }}
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section>
                <h2>Relatório por unidade</h2>
                <form method="get" action="{{ route('dashboard') }}" class="grid">
                    <label>Data inicial
                        <input type="date" name="start_date" value="{{ $report['start_date'] }}" required>
                    </label>
                    <label>Data final
                        <input type="date" name="end_date" value="{{ $report['end_date'] }}" required>
                    </label>
                    <button type="submit">Consultar período</button>
                </form>
                <p><strong>Total geral:</strong> R$ {{ intdiv($report['total_brl_cents'], 100) }},{{ str_pad((string) ($report['total_brl_cents'] % 100), 2, '0', STR_PAD_LEFT) }}</p>
                <table>
                    <thead>
                        <tr><th>Empresa</th><th>Unidade</th><th>Código</th><th>Total BRL</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($report['units'] as $unit)
                            <tr>
                                <td>{{ $unit['company'] }}</td>
                                <td>{{ $unit['name'] }}</td>
                                <td>{{ $unit['code'] }}</td>
                                <td>R$ {{ intdiv($unit['total_brl_cents'], 100) }},{{ str_pad((string) ($unit['total_brl_cents'] % 100), 2, '0', STR_PAD_LEFT) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>

            <div class="grid">
                <section>
                    <h2>Unidades cadastradas</h2>
                    @forelse ($units as $unit)
                        <p><strong>{{ $unit->name }}</strong> <span class="muted">({{ $unit->code }} — {{ $unit->company->name }})</span></p>
                    @empty
                        <p class="muted">Nenhuma unidade cadastrada.</p>
                    @endforelse
                </section>
                <section>
                    <h2>Empresas cadastradas</h2>
                    @forelse ($companies as $company)
                        <p>{{ $company->name }}</p>
                    @empty
                        <p class="muted">Nenhuma empresa cadastrada.</p>
                    @endforelse
                </section>
            </div>

            <section>
                <h2>Despesas cadastradas</h2>
                @if ($expenses->isEmpty())
                    <p class="muted">Nenhuma despesa cadastrada.</p>
                @else
                    <table>
                        <thead>
                            <tr><th>Data</th><th>Descrição</th><th>Fornecedor</th><th>Valor original</th><th>Conversão</th><th>Rateio</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($expenses as $expense)
                                <tr>
                                    <td>{{ $expense->expense_date->format('Y-m-d') }}</td>
                                    <td>{{ $expense->description }}</td>
                                    <td>{{ $expense->supplier }}</td>
                                    <td>{{ $expense->currency }} {{ intdiv($expense->amount_cents, 100) }},{{ str_pad((string) ($expense->amount_cents % 100), 2, '0', STR_PAD_LEFT) }}</td>
                                    <td>
                                        @if ($expense->conversion_status === 'pending')
                                            Pendente — aguardando cotação
                                        @elseif ($expense->conversion_status === 'converted')
                                            R$ {{ intdiv($expense->converted_amount_cents, 100) }},{{ str_pad((string) ($expense->converted_amount_cents % 100), 2, '0', STR_PAD_LEFT) }}
                                            <br><span class="muted">Cotação {{ $expense->exchange_rate }} em {{ $expense->exchange_rate_date->format('Y-m-d') }} ({{ $expense->exchange_rate_provider }})</span>
                                        @else
                                            Não necessária
                                        @endif
                                    </td>
                                    <td>
                                        @foreach ($expense->allocations as $allocation)
                                            {{ $allocation->unit->name }}: {{ $allocation->percentage }}%
                                            @if ($allocation->amount_cents !== null)
                                                (R$ {{ intdiv($allocation->amount_cents, 100) }},{{ str_pad((string) ($allocation->amount_cents % 100), 2, '0', STR_PAD_LEFT) }})
                                            @endif
                                            @if (! $loop->last) · @endif
                                        @endforeach
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>
        </main>
        <script>
            const allocationList = document.getElementById('allocations');
            const availableUnits = {{ Illuminate\Support\Js::from($units->map(fn ($unit) => ['id' => $unit->id, 'label' => $unit->name.' — '.$unit->company->name])) }};
            let nextAllocationIndex = allocationList?.querySelectorAll('.allocation').length ?? 0;

            document.getElementById('add-allocation')?.addEventListener('click', () => {
                const row = document.createElement('div');
                row.className = 'allocation';
                const unitLabel = document.createElement('label');
                unitLabel.append('Unidade');
                const unitSelect = document.createElement('select');
                unitSelect.name = `allocations[${nextAllocationIndex}][unit_id]`;
                unitSelect.required = true;
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = 'Selecione';
                unitSelect.append(placeholder);
                availableUnits.forEach((unit) => {
                    const option = document.createElement('option');
                    option.value = unit.id;
                    option.textContent = unit.label;
                    unitSelect.append(option);
                });
                unitLabel.append(unitSelect);

                const percentageLabel = document.createElement('label');
                percentageLabel.append('Percentual');
                const percentageInput = document.createElement('input');
                percentageInput.type = 'number';
                percentageInput.name = `allocations[${nextAllocationIndex}][percentage]`;
                percentageInput.min = '0.0001';
                percentageInput.max = '100';
                percentageInput.step = '0.0001';
                percentageInput.required = true;
                percentageLabel.append(percentageInput);

                const removeButton = document.createElement('button');
                removeButton.type = 'button';
                removeButton.className = 'remove-allocation';
                removeButton.setAttribute('aria-label', 'Remover unidade do rateio');
                removeButton.textContent = 'Remover';

                row.append(unitLabel, percentageLabel, removeButton);
                allocationList.append(row);
                nextAllocationIndex++;
            });

            allocationList?.addEventListener('click', (event) => {
                if (event.target.closest('.remove-allocation') && allocationList.querySelectorAll('.allocation').length > 1) {
                    event.target.closest('.allocation').remove();
                }
            });
        </script>
    </body>
</html>
