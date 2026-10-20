# Rateio de despesas entre unidades

Aplicação para cadastrar despesas compartilhadas por empresas de um mesmo grupo,
ratear seus valores entre unidades e consultar os totais em reais por período.

> **Status:** este repositório ainda não contém o projeto Laravel. Este documento
> registra a proposta inicial de arquitetura e as decisões para orientar a
> implementação. Os comandos de instalação e execução serão adicionados junto
> com a configuração do projeto.

## Problema e escopo

O sistema substitui uma planilha de despesas compartilhadas. Cada despesa tem
um valor, uma moeda e um rateio entre unidades. O rateio deve somar exatamente
100%, e os valores distribuídos devem fechar até o último centavo. Despesas em
USD são convertidas para BRL pela cotação da data da despesa. Também é possível
importar despesas por CSV e consultar totais por unidade em um período.

## Arquitetura proposta

- **Backend:** Laravel, organizado em camadas simples: controllers para HTTP,
  Form Requests para validação, serviços para regras de negócio e models para
  persistência. Evitar uma camada de abstração genérica antes de ela ser
  necessária.
- **Banco de dados:** MySQL. Usar migrations e chaves estrangeiras para
  representar as relações e proteger a integridade dos dados.
- **Interface:** Blade, com formulários e telas renderizados no servidor. É
  suficiente para o fluxo administrativo do teste e evita manter uma aplicação
  frontend separada.
- **API:** REST versionada em `/api/v1`, compartilhando validações e serviços
  com a interface para que as regras sejam aplicadas da mesma forma nos dois
  canais.
- **Ambiente local:** Docker Compose com Laravel Sail executa a aplicação e
  o MySQL em serviços reproduzíveis, sem exigir MySQL instalado no host.
- **Câmbio:** integração encapsulada em um serviço de cotação, inicialmente com
  a API pública Frankfurter. Despesas em USD podem ser persistidas como
  pendentes de conversão se o provedor estiver indisponível; uma fila pode
  tentar novamente sem bloquear ou descartar a despesa.
- **Importação:** processamento por linha, isolando cada registro válido em
  uma transação. Erros de uma linha são incluídos no relatório de retorno e não
  interrompem as demais.

### Modelo de dados inicial

- `companies`: empresas do grupo.
- `units`: unidades pertencentes a uma empresa.
- `expenses`: descrição, fornecedor, data, valor original, moeda, valor
  convertido para BRL quando disponível, cotação e estado da conversão.
- `expense_allocations`: unidade, percentual e valor alocado em centavos de BRL
  para cada despesa.

O estado de conversão deve distinguir despesas em BRL das despesas em USD
pendentes ou convertidas. A cotação usada e, quando disponível, sua data efetiva
devem ser persistidas para que relatórios históricos não dependam de consultar
novamente o provedor.

## Decisões e motivos

### Precisão do rateio

Não usar `float` para validar percentuais ou calcular dinheiro. Validar
percentuais com precisão decimal fixa e fazer cálculos monetários em centavos
inteiros. Após arredondar o total convertido para centavos, distribuir o valor
proporcionalmente. O método dos maiores restos atribui os centavos que sobrarem
às maiores frações; empates usam uma ordem estável de unidade. Assim, a soma
das alocações sempre é igual ao total da despesa, inclusive em divisões como
R$ 100,00 por três unidades.

### Falha do serviço de câmbio

Persistir a despesa em USD como pendente, em vez de rejeitá-la ou perdê-la,
atende ao requisito de resiliência. A conversão e a criação das alocações em
BRL ficam condicionadas à obtenção da cotação. Quando a fila estiver
configurada, novas tentativas podem concluir o processamento. Uma despesa ainda
pendente não deve ser apresentada como um total convertido no relatório.

### Importação parcial

Validar e processar cada linha separadamente. Uma transação por linha impede
que uma linha inválida deixe uma despesa parcialmente gravada, sem sacrificar
as linhas válidas do mesmo arquivo. O relatório deve identificar a linha e o
motivo do erro.

### Interface e API no mesmo domínio

Blade reduz o custo de implementação para o prazo do teste. A API versionada
continua disponível para unidades, despesas, importação e relatório. Tanto a
interface quanto a API devem chamar os mesmos serviços de aplicação, evitando
regras duplicadas.

### Princípios para toda a implementação

- Controllers e telas adaptam HTTP e apresentação; validações e regras de
  negócio ficam em componentes compartilhados, sem duplicar comportamento
  entre API e interface.
- Valores monetários são calculados em centavos inteiros e percentuais com
  precisão decimal fixa; `float` não é usado em cálculos financeiros.
- Uma despesa só entra nos totais em BRL quando sua conversão e seu rateio
  estiverem concluídos. Cotação e estado da conversão ficam persistidos.
- A importação isola cada linha em sua própria transação para manter válidas
  as demais linhas do arquivo.
- O MySQL é o banco da aplicação; testes automatizados verificam regras de
  negócio e contratos expostos pela API.

## Plano de implementação

1. **Fundação:** criar o projeto Laravel, configurar MySQL e migrations para
   empresas, unidades, despesas e rateios.
2. **Regra financeira:** implementar validação da soma de percentuais e
   distribuição exata dos centavos; cobrir com testes de unidade.
3. **Fluxo principal:** cadastrar unidades e despesas em BRL pela API e pela
   interface; adicionar testes de integração.
4. **Câmbio resiliente:** integrar cotação histórica USD/BRL, persistir a
   cotação e permitir que uma falha deixe a despesa pendente. Se o tempo
   permitir, adicionar fila e novas tentativas.
5. **CSV:** importar cada linha de forma independente e devolver um relatório
   de sucesso e erros por linha.
6. **Relatório:** somar alocações em BRL por unidade e período, expondo o
   resultado na API e na interface.
7. **Entrega:** documentar instalação, decisões e limitações, e validar o
   fluxo com a suíte de testes.

**Prioridade para o prazo de 4 a 6 horas:** garantir primeiro o modelo de dados,
o rateio exato e os testes; em seguida entregar o fluxo principal e o relatório.
Se o prazo apertar, a fila automática ou refinamentos da interface podem ficar
de fora, mas a despesa em USD não deve ser descartada quando o câmbio falhar.

## API planejada

Rotas iniciais sob `/api/v1`:

- `GET/POST /units`: listar e cadastrar unidades.
- `GET/POST /expenses`: listar e cadastrar despesas.
- `POST /expenses/import`: importar CSV e receber o relatório por linha.
- `GET /reports/units`: obter totais por unidade para um período.

Os contratos definitivos, filtros, paginação e códigos de erro serão
documentados quando as rotas forem implementadas.

## Testes essenciais

- Rateio rejeita percentuais cuja soma não seja exatamente 100%.
- Rateio de valores que geram frações de centavo fecha exatamente com o total.
- Conversão USD usa e persiste uma cotação histórica; falha no provedor não
  elimina a despesa.
- CSV importa linhas válidas mesmo quando outras linhas são inválidas e
  identifica os erros.
- Relatório retorna corretamente o total em BRL por unidade dentro do período.

## Fora do escopo inicial

- **Autenticação e autorização:** diferencial do teste; não implementar antes
  do fluxo central, mas são necessárias antes de disponibilizar a aplicação em
  produção.
- **Cadastro completo de empresas e gestão avançada de usuários:** manter o
  escopo focado no cadastro e uso das unidades e despesas. A necessidade de
  múltiplas empresas administradoras deve ser confirmada com o negócio.
- **Auditoria, edição concorrente e trilha de aprovação:** relevantes para
  controle financeiro real, mas não descritas no enunciado.
- **Frontend Vue:** Blade atende a interface simples solicitada sem acrescentar
  build e manutenção de uma aplicação cliente separada.

## Como executar

### Requisitos

- Docker Engine e Docker Compose.
- PHP 8.3 e Composer 2 para instalar as dependências do projeto.

### Instalação

```sh
git clone https://github.com/gabrielmNascimento/teste-gridmidia.git
cd teste-gridmidia
cp .env.example .env
composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
```

A aplicação fica disponível em <http://localhost:8000>. O endpoint de saúde da
API é <http://localhost:8000/api/v1/health>.

### Testes

```sh
./vendor/bin/sail test
```

### Parar os serviços

```sh
./vendor/bin/sail down
```

Para remover também os dados persistidos do MySQL, use
`./vendor/bin/sail down -v`.
