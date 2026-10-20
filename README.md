# Rateio de despesas entre unidades

Aplicação para cadastrar despesas compartilhadas por empresas de um mesmo grupo,
ratear seus valores entre unidades e consultar os totais em reais por período.

> **Status:** cadastro de empresas, unidades e despesas em BRL está disponível
> pela API e pela interface Blade. Despesas em USD são preservadas como
> pendentes de conversão. Integração de câmbio, importação CSV e relatório
> ainda serão implementados.

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

1. **Concluído — fundação:** projeto Laravel, configuração MySQL e migrations
   para empresas, unidades, despesas e rateios.
2. **Concluído — regra financeira:** validação exata dos percentuais e
   distribuição de centavos pelo método dos maiores restos.
3. **Concluído — cadastro inicial:** empresas e unidades, além de despesas em
   BRL e despesas USD pendentes, pela API e pela interface Blade.
4. **Próximo — câmbio resiliente:** buscar cotação histórica USD/BRL, persistir
   cotação e conversão e permitir nova tentativa quando o serviço falhar.
5. **Pendente — CSV:** importar cada linha independentemente e devolver um
   relatório de sucesso e erros por linha.
6. **Pendente — relatório:** somar alocações em BRL por unidade e período na
   API e na interface.
7. **Pendente — entrega:** completar documentação e validar todos os fluxos.

**Prioridade para o prazo de 4 a 6 horas:** garantir primeiro o modelo de dados,
o rateio exato e os testes; em seguida entregar o fluxo principal e o relatório.
Se o prazo apertar, a fila automática ou refinamentos da interface podem ficar
de fora, mas a despesa em USD não deve ser descartada quando o câmbio falhar.

## API disponível

Rotas atuais sob `/api/v1`:

- `GET/POST /companies`: listar e cadastrar empresas.
- `GET/POST /units`: listar e cadastrar unidades.
- `GET/POST /expenses`: listar e cadastrar despesas.
- `GET /health`: verificar a disponibilidade da API.

As rotas de listagem de unidades e despesas são paginadas. O cadastro de
despesas recebe `amount` como texto decimal com ponto e até duas casas
decimais, por exemplo `"1500.00"`, e `allocations` como uma lista de objetos
`unit_id` e `percentage`. O percentual também deve ser texto decimal, aceita até
quatro casas e a soma precisa ser exatamente 100%. Despesas BRL são rateadas
imediatamente. Despesas USD são gravadas com status `pending`, sem valor
convertido nem valores alocados em BRL, até a etapa de câmbio.

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
