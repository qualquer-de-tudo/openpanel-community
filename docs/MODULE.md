# QOpenPanel como módulo Laravel

O produto é o pacote Composer `qualquer-de-tudo/openpanel-community`, no namespace `QOpenPanel\Community`. A partir da 0.6, não há aplicação Laravel na raiz: somente o pacote e ferramentas próprias de teste/build. O instalador de servidor cria outro Laravel limpo e instala o módulo em `vendor`.

## Em um Laravel existente

Compatibilidade inicial: Laravel 13 (framework >=13.17) e Inertia Laravel 3.7+. A imagem validada usa PHP 8.5. Versões antigas e concorrência em Octane ainda não foram homologadas. Faça backup e teste em homologação antes de instalar num sistema existente.

```bash
composer config repositories.qopenpanel vcs https://github.com/qualquer-de-tudo/openpanel-community.git
composer require "qualquer-de-tudo/openpanel-community:^0.6@beta"
php artisan qopenpanel:install --force
```

O pacote é obtido pelo GitHub, sem depender de cadastro no Packagist. A descoberta de providers é automática. Se o hospedeiro desativar package discovery, registre `QOpenPanel\Community\Providers\QOpenPanelServiceProvider` em `bootstrap/providers.php`.

Abra `/qopenpanel/login` e crie a primeira conta antes de liberar acesso público. Use HTTPS ou túnel SSH. Não existe senha padrão. Também é possível criar a conta pelo terminal:

```bash
php artisan panel:account admin@example.com --name="Administrador"
```

A senha é solicitada sem eco. `qopenpanel:install` publica apenas `config/qopenpanel.php`, assets em `public/vendor/qopenpanel` e executa as migrations próprias do pacote. Não executa migrations pendentes do hospedeiro. O host deve ter `APP_KEY`, conexão de banco e sessões funcionais. Node e alterações no Vite do hospedeiro não são necessários: os assets compilados acompanham o pacote. Não edite arquivos em `vendor`.

## Em uma VPS sem Laravel

O host não precisa de PHP, Composer, Node ou Laravel. O instalador verifica e instala Git, curl, Docker Engine, Compose e Buildx em Ubuntu/Debian suportados, via apt e repositório oficial do Docker. Execute com root/sudo quando faltarem dependências. Veja [BOOTSTRAP.md](BOOTSTRAP.md) para baixar o script sem Git pré-instalado e conferir requisitos.

```bash
git clone https://github.com/qualquer-de-tudo/openpanel-community.git
cd openpanel-community
bash install.sh --private --email admin@example.com --name "Administrador"
```

Em seu computador, abra um túnel:

```bash
ssh -L 8080:127.0.0.1:8080 usuario@IP_DO_SERVIDOR
```

Acesse `http://127.0.0.1:8080/qopenpanel/login`, confira nome/e-mail e escolha a senha. Sem as opções de identidade, preencha tudo no formulário. O modo privado é obrigatório para execução sem terminal e não publica o serviço na Internet.

Para criar a conta pelo terminal e só depois abrir acesso por IP:

```bash
bash install.sh --ip 203.0.113.10 --email admin@example.com --name "Administrador"
```

Substitua o IP de exemplo pelo endereço do servidor. O script prepara Laravel e módulo dentro da imagem, sobe PHP-FPM/Caddy e SQLite persistente, solicita a senha sem eco e só então publica `http://SEU_IP:8080/qopenpanel`. IP usa HTTP: restrinja acesso à rede/IP administrativo ou use túnel até configurar HTTPS. Não exponha FastCGI 9000.

## Domínio inicial ou posterior

Com A/AAAA apontando corretamente e portas 80/443 disponíveis:

```bash
bash install.sh --domain painel.example.com
```

Execute no mesmo checkout. Se já houver conta, ela será preservada; caso contrário, o script solicita identidade e senha antes de publicar. A configuração anterior de `deploy.env` é copiada para `backups`. O fluxo reinicia serviços: planeje uma janela de manutenção. O endereço passa a `https://painel.example.com/qopenpanel`. Caddy solicita e renova certificados automaticamente; essa receita não implementa DNS-01 Cloudflare. Teste primeiro com DNS direto, sem proxy CDN.

Em um Laravel hospedeiro existente, configure domínio/HTTPS na infraestrutura desse aplicativo; o módulo não modifica seu servidor web.

## Isolamento e configuração

- Rotas e nomes: `/qopenpanel` e `qopenpanel.`; MCP: `/qopenpanel/mcp/panel`.
- Tabelas: `qopenpanel_*`; usuários e workspaces independentes dos usuários do host.
- Guard/provider: `qopenpanel`; login no hospedeiro não autoriza o painel. Logout do painel preserva os dados da sessão do host.
- Configuração: `qopenpanel.*`; não substitui `auth.defaults`, `catalog` ou o layout/Vite do host.
- Traduções, validação e estado Inertia ficam limitados às requisições do módulo. Os seis idiomas continuam disponíveis.

Para mudar o caminho, defina `QOPENPANEL_PATH=infra`, limpe/recrie caches e acesse `/infra/login`. Evite um caminho já usado. Para preparar a identidade inicial sem senha:

```bash
php artisan qopenpanel:configure --name="Administrador" --email=admin@example.com
```

O arquivo `storage/app/qopenpanel-setup.json`, modo 600, contém apenas nome/e-mail. O formulário inicial fecha quando existe uma conta. Para verificar sem modificar dados: `php artisan qopenpanel:install --check` (exit 0 quando existe conta).

## Atualização e backup

No host Laravel, faça backup antes e depois execute:

```bash
composer update qualquer-de-tudo/openpanel-community --with-dependencies
php artisan qopenpanel:install --force
php artisan config:clear
php artisan route:clear
```

O comando preserva a configuração publicada existente. Na instalação independente, atualize o checkout para a versão escolhida e reconstrua com o mesmo conjunto Compose. Em domínio, use sempre `docker compose --env-file deploy.env -f compose.yaml -f compose.production.yaml`; em IP configurado pelo script, `docker compose --env-file deploy.env`; em privado, `docker compose`.

Mantenha backup criptografado do volume `panel-data` inteiro, incluindo `app.key`, banco e diretórios privados. Preserve também `deploy.env` e os volumes Caddy. O entrypoint preserva a chave, limpa cache de configuração e aplica migrations. Rollback pode exigir o backup correspondente. Consulte também [INSTALL.md](INSTALL.md).

## Importar a beta 0.4

Esta é uma mudança de arquitetura. Preserve o volume original, a chave `app.key`/`APP_KEY` e um backup completo. Não execute o setup inicial nem crie usuários novos antes de importar. Depois de iniciar a imagem nova na mesma base SQLite:

```bash
docker compose exec panel php artisan qopenpanel:import-legacy --force
```

Acrescente os argumentos Compose de domínio/IP conforme a instalação. O comando exige os três marcadores de migrations da 0.4 e tabelas de destino vazias. Copia usuários, cotas, projetos, cofre cifrado, concessões, auditoria, versões, planos e templates numa transação. Não remove as tabelas originais e recusa uma segunda importação. A mesma chave é necessária para acessar o cofre; a importação não recifra segredos. O importador suporta SQLite nesta beta; outras bases exigem migração separada.

## Escopo e desenvolvimento

O instalador sobe os serviços do painel. Não instala os serviços gerenciados do catálogo: executor Linux, deploy real, e-mail, WAF e imposição física de cotas continuam pendentes.

No checkout de desenvolvimento, execute `npm run build:package` ao alterar o frontend e versione `resources/dist`. A demo continua usando `npm run build:demo`. O CI testa o módulo instalado em Laravel limpo, incluindo instalação, descoberta automática e caches de configuração/rotas.

Para atualizar o nome Composer da versão 0.5 e preservar o checkout/volumes após a renomeação do repositório, siga [BOOTSTRAP.md](BOOTSTRAP.md#atualizar-instalações-anteriores). Os testes do pacote agora usam `composer test`; comandos Artisan de desenvolvimento usam `php vendor/bin/testbench`, sem uma aplicação Laravel na raiz.
