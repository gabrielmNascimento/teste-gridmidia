# Rateio de despesas entre unidades

Aplicação para cadastrar despesas compartilhadas por empresas de um mesmo grupo,
ratear seus valores entre unidades e consultar os totais em reais por período.

> **Status:** cadastro de empresas, unidades e despesas está disponível pela
> API e pela interface Blade. Despesas USD são convertidas por uma fila usando
> cotação histórica. Importação CSV linha a linha e relatório por unidade e
> período estão disponíveis pela API e pela interface Blade.

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
  a API pública Frankfurter. Despesas em USD são persistidas como pendentes
  antes do processamento da fila, que tenta novamente se o provedor falhar.
- **Importação:** processamento por linha, isolando cada registro válido em
  uma transação. Erros de uma linha são incluídos no relatório de retorno e não
  interrompem as demais.

### Modelo de dados inicial

- `companies`: empresas do grupo.
- `units`: unidades pertencentes a uma empresa, com código estável e único
  usado para referenciá-las na importação CSV.
- `expenses`: descrição, fornecedor, data, valor original, moeda, valor
  convertido para BRL quando disponível, cotação e estado da conversão.
- `expense_allocations`: unidade, percentual e valor alocado em centavos de BRL
  para cada despesa.

O estado de conversão deve distinguir despesas em BRL das despesas em USD
pendentes ou convertidas. A cotação usada e, quando disponível, sua data efetiva
devem ser persistidas para que relatórios históricos não dependam de consultar
novamente o provedor.

Valores de despesas e alocações são armazenados em centavos inteiros. O
percentual de cada alocação usa decimal com quatro casas, evitando ponto
flutuante e permitindo validar a soma com precisão fixa. O valor alocado fica
nulo enquanto a despesa USD aguarda conversão, para que uma cotação indisponível
nunca pareça um total BRL. Chaves estrangeiras impedem a remoção de empresas,
unidades ou despesas que ainda tenham dados dependentes.

## Decisões e motivos

### Precisão do rateio

Não usar `float` para validar percentuais ou calcular dinheiro. Validar
percentuais com precisão decimal fixa e fazer cálculos monetários em centavos
inteiros. Percentuais aceitam até quatro casas decimais e precisam somar
exatamente 100%. Após arredondar o total convertido para centavos, distribuir o
valor proporcionalmente. O método dos maiores restos atribui os centavos que
sobrarem às maiores frações; empates são resolvidos pelo menor identificador de
unidade. Assim, o resultado é determinístico e a soma das alocações sempre é
igual ao total da despesa, inclusive em divisões como R$ 100,00 por três
unidades.

### Falha do serviço de câmbio

Persistir a despesa em USD como pendente, em vez de rejeitá-la ou perdê-la,
atende ao requisito de resiliência. A conversão e a criação das alocações em
BRL ficam condicionadas à obtenção da cotação. Quando a fila estiver
ativa, o job tenta processar a conversão até cinco vezes, com espera crescente.
O serviço busca a taxa da data da despesa e registra a data efetiva retornada
pelo provedor, que pode ser anterior em fins de semana ou feriados. A taxa é
extraída como texto decimal e multiplicada por centavos com aritmética inteira,
sem depender de `float` ou da extensão BCMath. O total é arredondado uma vez
para centavos, pelo método metade para cima, antes da distribuição pelo método
dos maiores restos. Uma despesa ainda pendente não deve ser apresentada como
um total convertido no relatório.

### Importação parcial

Validar e processar cada linha separadamente. Uma transação por linha impede
que uma linha inválida deixe uma despesa parcialmente gravada, sem sacrificar
as linhas válidas do mesmo arquivo. O importador informa a linha e o motivo de
cada erro, e o registro de despesas usa o mesmo serviço do formulário e da API.
O CSV tem cabeçalho obrigatório e um registro por despesa; o campo de rateios
referencia os códigos estáveis das unidades.

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

1. **Concluído — fundação:** projeto Laravel, configuração MySQL e migrations
   para empresas, unidades, despesas e rateios.
2. **Concluído — regra financeira:** validação exata dos percentuais e
   distribuição de centavos pelo método dos maiores restos.
3. **Concluído — cadastro inicial:** empresas e unidades, além de despesas em
   BRL e despesas USD pendentes, pela API e pela interface Blade.
4. **Concluído — câmbio resiliente:** buscar e persistir cotação histórica
   USD/BRL, converter sem ponto flutuante e processar falhas com nova tentativa
   na fila.
5. **Concluído — CSV:** importar cada linha independentemente e devolver um
   resumo de sucesso e erros por linha.
6. **Concluído — relatório:** somar alocações convertidas em BRL por unidade e
   período na API e na interface.
7. **Concluído — entrega funcional:** documentar contratos e validar os fluxos
   principais com testes automatizados.

Autenticação e autorização continuam fora do escopo do teste, mas são
necessárias antes de disponibilizar o sistema em produção.

## API disponível

Rotas atuais sob `/api/v1`:

- `GET/POST /companies`: listar e cadastrar empresas.
- `GET/POST /units`: listar e cadastrar unidades.
- `GET/POST /expenses`: listar e cadastrar despesas.
- `POST /expenses/import`: importar despesas por CSV; retorna quantidades
  importadas e erros associados às linhas.
- `GET /reports/units?start_date=AAAA-MM-DD&end_date=AAAA-MM-DD`: consultar
  alocações em BRL por unidade e total geral no período inclusivo. Unidades sem
  despesas aparecem com total zero; despesas USD pendentes são excluídas.
- `GET /health`: verificar a disponibilidade da API.

As rotas de listagem de unidades e despesas são paginadas. O cadastro de
despesas recebe `amount` como texto decimal com ponto e até duas casas
decimais, por exemplo `"1500.00"`, e `allocations` como uma lista de objetos
`unit_id` e `percentage`. O percentual também deve ser texto decimal, aceita até
quatro casas e a soma precisa ser exatamente 100%. Despesas BRL são rateadas
imediatamente. Despesas USD são gravadas com status `pending`, sem valor
convertido nem valores alocados em BRL, até o worker concluir a conversão.

Despesas em USD são convertidas de forma assíncrona pela cotação histórica do
Frankfurter para a data da despesa. Se não houver cotação naquela data, o
provedor retorna a data efetiva mais recente disponível; essa data, a taxa e o
provedor ficam persistidos na despesa. O valor convertido é arredondado uma
única vez para centavos (metade para cima) antes do rateio. Falhas mantêm a
despesa pendente e o job é tentado até cinco vezes, com intervalos crescentes.
Para processar a fila, mantenha um worker ativo:

```bash
./vendor/bin/sail artisan queue:work
```

O serviço `laravel.test` deve estar iniciado para executar o comando via Sail.
Em execução fora do Sail, configure o ambiente para alcançar o MySQL e execute
`php artisan queue:work`.

Exemplo de cadastro:

```json
{
  "description": "Licença CRM",
  "supplier": "Fornecedor X",
  "expense_date": "2026-09-01",
  "amount": "1500.00",
  "currency": "BRL",
  "allocations": [
    {"unit_id": 1, "percentage": "50"},
    {"unit_id": 2, "percentage": "30"},
    {"unit_id": 3, "percentage": "20"}
  ]
}
```

O formulário Blade fica na página inicial (`/`). A API e a interface usam o
mesmo serviço de registro e cálculo de rateio.

### Formato de importação CSV

O arquivo deve ser UTF-8, separado por vírgulas, com este cabeçalho (a ordem
das colunas pode variar):

```csv
description,supplier,expense_date,amount,currency,allocations
Internet,Fornecedor,2026-10-01,100.00,BRL,unidade-centro=60;unidade-norte=40
Licença,Fornecedor,2026-10-02,25.00,USD,unidade-centro=100
```

Cada linha representa uma despesa. `expense_date` usa `AAAA-MM-DD`; `amount`
usa ponto decimal e no máximo duas casas; `currency` aceita `BRL` ou `USD`.
Em `allocations`, cada item é `codigo-da-unidade=percentual`, separado por
ponto e vírgula. O código precisa existir e os percentuais devem somar
exatamente 100%, com até quatro casas decimais. Campos de texto que contiverem
vírgulas devem seguir as regras de escape padrão do CSV. O arquivo pode ter até
10 MB. Erros de uma linha não impedem a importação das demais; a resposta inclui
o número da linha e a causa. Despesas USD válidas ficam pendentes e são
enfileiradas para conversão como no cadastro manual.

O relatório da interface aparece na página inicial e permite selecionar as
datas inicial e final. A API exige os mesmos parâmetros; ambos incluem os dois
limites do período e excluem despesas não convertidas.

## Testes essenciais

- Rateio rejeita percentuais cuja soma não seja exatamente 100%.
- Rateio de valores que geram frações de centavo fecha exatamente com o total.
- API e interface cadastram despesas com rateios idênticos e rejeitam somas
  percentuais inválidas.
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
