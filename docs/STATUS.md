# Escopo da versão 0.1

| Área | Situação |
| --- | --- |
| Primeiro acesso, login, logout, tentativas limitadas | Implementado |
| Dados separados por proprietário | Implementado e testado |
| Cotas de projetos, bancos e reserva de espaço | Implementado no backend |
| CPU/RAM/disco reais limitados no Linux | Pendente de agente |
| Sites, bancos, runtimes e Docker Compose | Cadastro de configurações planejadas |
| Instalação e atualização de serviços | Pendente |
| Deploy de Laravel, React, Next.js e outros | Pendente |
| Repositórios OAuth, webhooks e deploy por IA | Pendente |
| HTTPS do próprio painel | Caddy na configuração de domínio |
| Certificados de sites gerenciados e Cloudflare DNS API | Pendente |
| Mailcow, WAF, Fail2ban, antivírus e cron | Cadastro de opções; execução pendente |
| Estatísticas reais de tráfego e recursos | Pendente |
| Marketplace | Catálogo de planejamento, sem homologação das receitas |
| Demo estática | Interface em modo leitura com dados fictícios |

Configurações salvas têm status `planned`. A aplicação não recebe socket Docker, acesso root nem credenciais de nuvem. Não há serviço gerenciado de hospedagem ou promessa de compatibilidade universal.

Próximos marcos: agente Linux autenticado e auditável; receitas versionadas e testadas; deploys e certificados; isolamento efetivo por conta; backups e recuperação; integrações Git/IA com permissões restritas.

## MCP (incremento da versão 0.2)

Endpoint autenticado, permissões por conta/recurso, cofre criptografado sem retorno de valores, auditoria, versões de configuração, planos presos a commit e broker GitHub. Veja [MCP.md](MCP.md). Execução Linux, OAuth interativo e deploy efetivo continuam pendentes.

## Beta 0.3.0-beta.1

Botão de instalação na demo, seis idiomas iniciais e catálogos compartilhados React/Laravel. Traduções verificadas no CI. A classificação beta se refere à experiência do painel; o executor Linux e os deploys efetivos continuam pendentes.

## Beta 0.4.0-beta.1

Templates de projeto versionados, catálogo comunitário via pull requests, exportação ZIP com lockfile, atualização e rollback com snapshots. Nenhum script de template é executado durante a instalação. O executor Linux permanece pendente.

## 0.5.0-beta.1 — módulo Composer

Pacote auto descoberto em `vendor`, tabelas/guard/configuração isolados, assets compilados e instalador que cria Laravel limpo no Docker. Importação opt-in da base SQLite 0.4, preservando originais. Compatibilidade inicial Laravel 13/Inertia 3; Octane não homologado. Executor Linux e imposição física de cotas continuam pendentes. [Arquitetura e instalação](MODULE.md).
