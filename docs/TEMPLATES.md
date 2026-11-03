# Templates de projetos da comunidade

Templates criam arquivos iniciais de um projeto e uma configuração no painel. A instalação registra uma versão exata e permite exportar o projeto em ZIP. Não instala pacotes no host, executa hooks ou publica aplicações automaticamente: o executor Linux continua pendente.

## Instalar

Abra **Templates** no menu, escolha um pacote e clique em **Instalar template**. Selecione a versão, nomeie o projeto e, opcionalmente, informe o domínio. O backend verifica compatibilidade, cotas e permissões. Depois use **Exportar projeto** para baixar o código e o lockfile.

A demo pública permite explorar o catálogo, seleção de versão e um histórico fictício. Não grava instalações nem exporta projetos reais.

Pacotes iniciais:

- `static-site@1.0.0`: HTML e CSS.
- `static-site@1.1.0`: evolução do mesmo site, mantendo a versão anterior disponível.
- `react-vite@1.0.0`: React/Vite com dependências diretas exatas e `package-lock.json` incluído.

## Versionamento

Cada release usa uma versão estável SemVer `MAJOR.MINOR.PATCH`. Um ID e versão publicados não podem ser alterados nem removidos: mudanças exigem outro número de versão. Novas dependências, correções e traduções de metadados também mudam o conteúdo do pacote e precisam de uma nova release do template.

O arquivo `resources/templates/catalog.lock.json` registra SHA-256 do manifesto canônico. O servidor verifica o digest antes de disponibilizar a release. O CI compara o lock com a base do pull request ou commit anterior e recusa reescrita de releases existentes. Referência: [SemVer](https://semver.org/).

Na instalação, o banco preserva uma cópia do manifesto e seus arquivos. Atualizações exigem a revisão atual do recurso e uma versão maior, compatível com a mesma engine. Alterações concorrentes são recusadas. A reserva de disco não é reduzida automaticamente num rollback e qualquer aumento é verificado contra a cota.

Instalação, atualização e rollback acrescentam entradas ao histórico e capturam o estado no versionamento do projeto. O manifesto do projeto inclui ID, versão e digest do template. **Restaurar versão** usa a cópia histórica armazenada, criando uma nova revisão em vez de editar o passado. A restauração genérica de configuração via MCP recusa versões de template diferentes: use o rollback de templates para trocar os arquivos explicitamente.

O ZIP contém `qopenpanel-template.lock.json` com a identidade do pacote, versão, digest do manifesto e hashes dos arquivos. Mantenha esse arquivo no seu repositório Git. Os hashes detectam alterações de conteúdo; não são assinaturas do autor nem prova de segurança.

## Contribuir com um template

1. Faça um fork e crie uma branch no [repositório](https://github.com/qualquer-de-tudo/openpanel-community).
2. Gere o arquivo inicial:

```bash
npm run templates:new -- meu-template 1.0.0 "Nome do autor"
```

3. Edite `resources/templates/meu-template/1.0.0.json`: metadados, nomes e descrições traduzidos, licença, engine, espaço mínimo e conteúdo dos arquivos.
4. Registre e valide:

```bash
npm run templates:lock
npm run templates:check
composer test -- --filter=TemplatesTest
node --test tests/Unit/templates-policy.test.mjs
npm run build:demo
```

5. Envie um pull request com o manifesto e o lock atualizado. Explique o objetivo, licença, dependências, como testar e se os arquivos contêm scripts de build. Os mantenedores revisam antes de integrar o catálogo distribuído.

Para uma nova versão de um template existente, copie seu manifesto para outro arquivo de versão no mesmo diretório e edite o campo `version`. Preserve os arquivos anteriores e rode os comandos acima. O gerador não sobrescreve um arquivo existente.

## Formato do manifesto, schema 1

| Campo | Conteúdo |
| --- | --- |
| `schema` | Inteiro `1` |
| `id` | Identificador em letras minúsculas, números e hífens |
| `version` | Versão exata estável, por exemplo `1.2.0`; `latest` e intervalos não são aceitos |
| `name` / `description` | Objetos por idioma; `en` e `pt_BR` obrigatórios, demais idiomas opcionais com fallback |
| `author` | Autor ou organização |
| `license` | Identificação da licença; inclua os avisos exigidos nos arquivos distribuídos |
| `panel_min` | Versão mínima do painel, inclusive beta quando aplicável |
| `engine` | Uma opção da categoria `sites` do catálogo do painel |
| `disk_mb` | Reserva mínima de disco em MB |
| `files` | Objeto com caminho relativo e conteúdo textual UTF-8 |

Veja os manifestos incluídos como exemplos completos. O verificador PHP é a autoridade para o formato. Campos desconhecidos, inclusive `post_install`, são recusados. Não há um mecanismo de hooks, download remoto ou instalação de ZIP fornecido por terceiros.

Limites por manifesto: até 100 arquivos, 128 KiB por arquivo, 512 KiB de conteúdo total e 1 MiB de manifesto canônico. O catálogo é limitado a 500 releases. Caminhos absolutos, traversal, diretórios ocultos, arquivos comuns de credenciais, nomes de dispositivo do Windows, conflitos de nomes e o nome reservado do lockfile são rejeitados. Esta versão distribui apenas arquivos textuais, não binários.

Nunca inclua tokens, senhas, dados de clientes ou chaves privadas. Use o cofre do painel depois da instalação para planejar credenciais por referências opacas. Um template pode conter código de aplicação ou scripts em `package.json`; eles não são executados ao instalar no painel, mas devem ser revisados antes de rodar o projeto exportado. Validação estrutural e checksums não substituem revisão de código, análise de dependências ou auditoria.

## MCP

A ferramenta `project_templates` oferece `catalog`, `inspect`, `install`, `update` e `rollback`. Leitura exige `panel:read`; escrita exige `panel:write` e `versions:write`, respeitando os recursos autorizados na concessão. Não retorna valores do cofre nem executa código de templates. Os IDs das ferramentas e ações permanecem estáveis em todos os idiomas.

## Limites atuais

Não há atualização automática de arquivos em servidores ou repositórios Git, merge de modificações externas, restauração de banco de dados, assinaturas de autores ou registro público com upload automático. O projeto exportado deve ser versionado em Git pelo proprietário. Download e alterações de template são limitados à conta dona do projeto; o catálogo é distribuído com a versão do painel.
