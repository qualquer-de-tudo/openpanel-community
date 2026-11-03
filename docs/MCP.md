# MCP para IAs — QOpenPanel

O endpoint `POST /qopenpanel/mcp/panel` implementa MCP com o pacote oficial `laravel/mcp`. O cliente conecta por Streamable HTTP com uma credencial dedicada ou pela ponte local `bin/mcp-bridge.mjs`, que lê a credencial em um arquivo privado sem colocá-la no contexto de ferramentas do modelo. A demo pública estática não oferece MCP.

## O que a IA pode fazer

| Ferramenta | Função | Permissão |
| --- | --- | --- |
| `panel_inspect` | Catálogo, recursos, cotas, capacidades e auditoria resumida da concessão | `panel:read` |
| `panel_configure` | Criar, alterar e remover configurações de todas as categorias do painel | `panel:write` |
| `vault_manage` | Gerar, rotacionar e vincular credenciais; listar somente metadados | `secrets:manage` |
| `project_versions` | Histórico, inspeção e snapshots imutáveis de configuração; restauração em nova revisão | leitura: `panel:read`; gravação: `versions:write`; restauração também `panel:write` |
| `project_versions`, ação `plan_deploy` | Plano preso a uma versão e commit de origem | `deploy:plan` |
| `git_repository` | Consultar branches/commits e árvore, criar branches/tags e commitar um arquivo fonte | leitura: `git:read`; gravação: `git:write` |

A IA atua somente na conta autenticada e nos recursos permitidos na concessão. Não existem ferramentas para criar concessões, aumentar cotas, ler senhas, exportar ambientes, executar SQL/shell ou acessar outras contas. O catálogo é descoberto pelo MCP; não depende do modelo memorizar opções.

## Atualizar a instalação

```bash
git pull --ff-only
docker compose up -d --build
```

No modo domínio, mantenha os argumentos `--env-file deploy.env -f compose.yaml -f compose.production.yaml` usados na instalação. As migrations criam o cofre, concessões, auditoria, versões e planos. Faça backup antes da atualização.

## Criar uma conexão sem mostrar a chave à IA

Execute como operador humano no servidor. Não peça a um modelo com acesso a shell para ler o arquivo gerado.

```bash
# Diretório privado no volume persistente do painel.
docker compose exec panel sh -c 'mkdir -p /data/private-mcp && chmod 700 /data/private-mcp'

# Acesso inicial somente de leitura, por sete dias.
docker compose exec panel php artisan panel:mcp-grant admin@example.com \
  /data/private-mcp/client.mcp-private.json \
  --endpoint=https://painel.seudominio.com/qopenpanel/mcp/panel \
  --scopes=panel:read --days=7
```

O comando mostra apenas o ID para revogação, nunca o token. O banco guarda somente SHA-256 do token de acesso. A validade máxima é 30 dias e cada credencial tem 48 bytes de aleatoriedade criptográfica.

Para automação de configuração, cofre e versões, crie outro arquivo com permissões explícitas, por exemplo `--scopes=panel:read,panel:write,secrets:manage,versions:write,deploy:plan`. Para limitar a recursos existentes, acrescente `--projects=1,2`. Uma concessão limitada a IDs não pode criar recursos fora dessa lista. Permissão de escrita Git é separada e não vem no padrão.

Copie o arquivo privado para o computador onde o cliente MCP roda, usando seu canal administrativo seguro. Por exemplo, execute `docker compose cp panel:/data/private-mcp/client.mcp-private.json ./private-mcp/client.mcp-private.json` no servidor, dentro de um diretório local com modo 700, e transfira por SCP. No destino Linux/macOS, aplique modo 600 ao arquivo. No Windows, restrinja a ACL ao usuário do cliente; a ponte não ajusta ACLs automaticamente.

Configuração do cliente MCP local, sem valores de chave:

```json
{
  "mcpServers": {
    "qopenpanel": {
      "command": "node",
      "args": [
        "/caminho/openpanel-community/bin/mcp-bridge.mjs",
        "/diretorio/privado/client.mcp-private.json"
      ]
    }
  }
}
```

Requer Node 24. Use caminhos absolutos. Adapte o formato ao cliente MCP escolhido. A ponte usa stdio para o cliente e HTTPS para o painel; HTTP é aceito somente para loopback, como em um túnel SSH. Não segue redirecionamentos, não imprime a configuração e sanitiza erros de transporte. No Linux/macOS recusa arquivo com permissões de grupo/outros ou de outro proprietário.

O arquivo privado contém a credencial de transporte porque o software cliente precisa se autenticar. Ele não deve ser enviado como mensagem, anexado a um chat ou colocado em repositório. Modelos que recebem somente as ferramentas MCP não recebem seu conteúdo. Clientes HTTP que não usam a ponte precisam configurar o cabeçalho de autorização em seu armazenamento privado. OAuth interativo e descoberta de autorização ainda não estão implementados; não prometa compatibilidade automática com clientes que exigem esse fluxo.

## Fluxo sem senhas no contexto do modelo

1. `panel_inspect` descobre os recursos e cotas.
2. `panel_configure` prepara um projeto com URL pública de repositório, sem senha ou parâmetros.
3. `vault_manage`, ação `generate`, cria um segredo aleatório no servidor. A resposta contém somente UUID, finalidade e versão.
4. `vault_manage`, ação `bind`, associa `DB_PASSWORD` ao UUID. O valor continua no cofre.
5. `project_versions`, ação `snapshot`, congela a configuração e as referências/versionamentos dos segredos, opcionalmente com `source_sha` de 40 caracteres. A numeração de snapshots é independente da revisão de configuração: commits diferentes podem ter versões diferentes com a mesma configuração. Um snapshot idêntico ao último é idempotente.
6. `plan_deploy` cria um plano apontando para essa versão. Nesta versão permanece `blocked_agent`: nenhum serviço é instalado nem deploy executado.

Segredos de aplicação usam 48 bytes de aleatoriedade e criptografia autenticada do Laravel em repouso. A chave mestra é `APP_KEY`; proteja o volume e os backups que a contêm. Isto não implementa HSM/KMS, isolamento do administrador do host ou rotação automática da chave mestra.

O operador pode materializar as variáveis em um arquivo privado fora do MCP:

```bash
docker compose exec panel php artisan panel:vault-materialize admin@example.com \
  1 /data/private-mcp/runtime.env
```

O comando não escreve valores no terminal, não sobrescreve arquivos existentes e recusa destinos de stream como `php://stdout`. O arquivo só contém segredos de aplicação vinculados. Credenciais GitHub nunca são materializadas por esse comando. A instalação ainda não injeta automaticamente o arquivo em serviços porque o executor Linux não existe.

Rotacionar no cofre não atualiza senhas em um banco externo nem reinicia aplicações. Isso requer coordenação com o futuro provisionador. Uma restauração de configuração recusa referências/versionamentos de segredo diferentes dos atuais; não reverte segredos antigos silenciosamente.

## GitHub e versionamento de código

Uma sequência aleatória local não é um token válido de GitHub, Cloudflare ou de outro provedor. O administrador emite a credencial no provedor e a importa por arquivo privado, fora de qualquer ferramenta do modelo. Prefira token com expiração e permissão limitada ao repositório pretendido.

```bash
docker compose exec panel php artisan panel:vault-import-github admin@example.com \
  1 /data/private-mcp/github-token.txt
```

O arquivo de origem deve ter modo 600 no Linux. O projeto 1 precisa ter uma URL HTTPS de repositório GitHub configurada. O comando retorna somente um UUID. A credencial fica presa ao ID do recurso e à URL exata do repositório: alterar a URL no painel não permite reutilizá-la em outro repositório. Revogue a credencial no próprio GitHub quando não for mais necessária e remova o arquivo de importação da sua infraestrutura conforme sua política.

O broker chama somente `https://api.github.com`, sem redirecionamentos, e usa o token internamente. As respostas contêm nomes de branches, SHAs, datas e confirmação de operações, sem conteúdo de arquivos ou erros brutos do provedor. Consultas retornam até 50 branches, 30 commits ou 200 entradas da árvore por chamada; a resposta sinaliza árvores truncadas.

Com `git:write`, a IA pode criar branches/tags e usar `commit_file`. Essa operação exige o commit de origem esperado, cria uma árvore/commit com esse pai e tenta atualizar a referência com `force=false`; se outra linha de desenvolvimento avançou de forma incompatível, o GitHub recusa. Não há force push ou exclusão de repositórios. Caminhos ocultos, traversal, workflows e caminhos típicos de credenciais são bloqueados. Não há suporte a pull requests, merge, checkout local ou GitLab nesta versão.

Permitir escrita de código é uma capacidade poderosa: alterações em uma aplicação ou workflows já existentes podem executar código depois que você decidir rodá-los. Use branches protegidas, revisão e concessões estreitas. Nenhum cofre consegue garantir que segredos de runtime permaneçam invisíveis a uma IA que também possa executar código arbitrário com acesso a esses segredos. O contrato de proteção aqui é: **as ferramentas MCP não leem, retornam nem exportam valores do cofre para o modelo**. Essa fronteira não se estende a shell, outros conectores, arquivos privados acessíveis ao mesmo usuário, código executado, backups ou comprometimento do host.

## Auditoria e revogação

```bash
docker compose exec panel php artisan panel:mcp-revoke UUID_DA_CONCESSAO
```

A revogação vale para a próxima requisição. Chamadas de ferramentas registram conta, concessão, ferramenta, horário e resultado. Argumentos, conteúdo de arquivos, tokens e valores do cofre não são gravados na auditoria. `panel_inspect` mostra os últimos registros da própria concessão. Auditoria é um registro no banco, não um ledger inviolável nem um log externo. Não exponha ferramentas de debug Laravel Boost em produção.

O endpoint limita tamanho da requisição e frequência, recusa chamadas de origem de navegador e requer seu próprio token por requisição. Limites de operações e cotas também são validados no backend. Use HTTPS público, banco e disco protegidos e backups privados. Revogar uma concessão MCP não revoga um token já emitido pelo GitHub.

## Testes

```bash
php artisan test
node --test tests/Unit/mcp-bridge.test.mjs
```

Os testes HTTP verificam protocolo MCP, isolamento, escopos, expiração/revogação, cofre sem valores nas respostas, versões, conflitos e GitHub simulado. A ponte é testada com servidor local de teste. Esses testes não equivalem a uma auditoria de segurança independente.

Referências técnicas: [Laravel MCP](https://laravel.com/framework/docs/mcp), [práticas de segurança MCP](https://modelcontextprotocol.io/docs/2025-11-25/tutorials/security/security_best_practices) e [API Git Data do GitHub](https://docs.github.com/en/rest/git).

## Templates de projeto

A ferramenta project_templates fornece catálogo, inspeção, instalação, atualização e rollback de templates fixados por versão. Gravação exige panel:write e versions:write. Consulte [templates da comunidade](TEMPLATES.md).

Na 0.5, o endpoint acompanha `QOPENPANEL_PATH`. Concessões antigas podem precisar do novo endpoint no arquivo privado do cliente; nunca envie seu conteúdo ao modelo. Consulte [MODULE.md](MODULE.md) para importar a base anterior preservando a chave do cofre.
