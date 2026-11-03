# Segurança

Esta versão 0.1 é um MVP de planejamento. Não oferece provisionamento Linux nem isolamento efetivo de hardware. Use uma VM dedicada para avaliação.

Para relatar uma vulnerabilidade, use a aba Security > Report a vulnerability do repositório, quando disponível. Se o canal privado estiver indisponível, abra uma issue pedindo contato privado, sem publicar detalhes de exploração ou segredos. Nunca coloque credenciais ou dados de clientes em issues públicas.

Antes de acesso público: crie a conta inicial via túnel SSH, ative HTTPS, mantenha SSH restrito, preserve backups e atualize dependências. O primeiro acesso a uma instalação sem usuários permite criar a conta inicial. A demonstração estática é separada da aplicação real e não se conecta a um servidor.

O MCP do painel usa credenciais dedicadas com escopos, validade e revogação. O cofre não tem operação MCP de leitura/exportação de valores. Proteja APP_KEY, arquivos do cliente e materializações de runtime. O isolamento não cobre uma IA com shell ou execução de código que possa ler o ambiente do serviço. Consulte [o modelo de segurança do MCP](docs/MCP.md).
