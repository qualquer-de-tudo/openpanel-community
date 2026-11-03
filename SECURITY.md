# Segurança

Esta versão 0.1 é um MVP de planejamento. Não oferece provisionamento Linux nem isolamento efetivo de hardware. Use uma VM dedicada para avaliação.

Para relatar uma vulnerabilidade, use a aba Security > Report a vulnerability do repositório, quando disponível. Se o canal privado estiver indisponível, abra uma issue pedindo contato privado, sem publicar detalhes de exploração ou segredos. Nunca coloque credenciais ou dados de clientes em issues públicas.

Antes de acesso público: crie a conta inicial via túnel SSH, ative HTTPS, mantenha SSH restrito, preserve backups e atualize dependências. O primeiro acesso a uma instalação sem usuários permite criar a conta inicial. A demonstração estática é separada da aplicação real e não se conecta a um servidor.
