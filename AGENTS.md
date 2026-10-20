# Diretrizes do projeto

Consulte o [README.md](README.md) para o escopo e as decisões arquiteturais
completas; mantenha-as como prioridade em toda mudança.

## Arquitetura

- Use Laravel com MySQL, API REST versionada em `/api/v1` e interface Blade.
- Mantenha regras de negócio em componentes compartilhados; controllers,
  validações HTTP e telas não devem duplicar regras entre API e interface.
- Prefira a estrutura e os recursos nativos do Laravel. Não adicione camadas,
  dependências ou abstrações sem necessidade demonstrável.

## Integridade financeira

- Nunca use `float` para dinheiro ou percentuais. Represente valores monetários
  em centavos inteiros e valide percentuais com precisão decimal fixa.
- Garanta que os percentuais somem exatamente 100% e que as alocações em
  centavos fechem exatamente com o total.
- Preserve cotação e estado da conversão. Despesas USD pendentes não podem ser
  incluídas em totais BRL como se estivessem convertidas.
- Importe CSV por linha, sem deixar que uma linha inválida reverta as válidas.

## Validação e comunicação

- Adicione testes às regras de negócio e aos contratos HTTP afetados; execute
  `./vendor/bin/sail test` quando o ambiente Docker estiver disponível.
- Documente decisões e comandos de execução no README, em português.
- Se solicitado a criar commits, use mensagens em português e não inclua
  coautoria ou menção ao Copilot.
