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
