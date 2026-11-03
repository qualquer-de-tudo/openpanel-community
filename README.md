# openpanel-community

**Módulo Laravel gratuito e open source para organizar a hospedagem de aplicações Linux.** Laravel 13 + React + Inertia, com licença MIT.

[Demo interativa](https://qualquer-de-tudo.github.io/openpanel-community/) · [Instalar em uma VPS](docs/INSTALL.md) · [AWS, Azure e outros provedores](docs/PROVIDERS.md) · [Contribuir](CONTRIBUTING.md) · [MCP para IAs](docs/MCP.md)

> **Versão 0.6.0-beta.1 — módulo Composer.** Login, contas isoladas, cotas e cadastro de configurações funcionam. Instalação de serviços, deploys, SSL dos sites, e-mail, WAF e limites reais de hardware ainda dependem do futuro agente Linux. Consulte [o escopo](docs/STATUS.md).

## Módulo instalável em Laravel

O produto agora fica em `vendor`, com provider auto descoberto, autenticação, tabelas, rotas e assets próprios. Compatibilidade inicial: Laravel 13 e Inertia 3.7+. O repositório contém somente o pacote, seus testes, frontend, documentação e infraestrutura de instalação; não inclui uma aplicação Laravel.

```bash
composer config repositories.qopenpanel vcs https://github.com/qualquer-de-tudo/openpanel-community.git
composer require "qualquer-de-tudo/openpanel-community:^0.6@beta"
php artisan qopenpanel:install --force
```

Crie a primeira conta em `/qopenpanel/login` antes de publicar. Para servidor sem Laravel, execute `bash install.sh --private`: ele prepara Laravel e instala o pacote dentro do Docker. Também aceita `--ip SEU_IP` ou `--domain painel.example.com`, criando a conta pelo terminal antes da exposição pública. [Instalação, domínio posterior e migração da beta anterior](docs/MODULE.md).

![Dashboard de demonstração do OpenPanel](docs/demo-preview.png)

## Idiomas

Português, English, Français, Español, 简体中文 e 日本語, com seletor e preferência persistida. [Como traduzir ou adicionar um idioma](docs/TRANSLATING.md).

## Demonstração gratuita

A demo usa a mesma interface React, com dados fictícios e operações de gravação bloqueadas. Pode ser publicada gratuitamente no GitHub Pages por meio do workflow incluído. Nenhum acesso administrativo, segredo ou servidor é exposto na demo. [Como publicar](docs/PUBLISHING.md).

## Instalação rápida

Em Ubuntu ou Debian suportado, o instalador prepara as dependências automaticamente. Baixe o arquivo da release ou use curl, se já disponível:

```bash
curl --proto '=https' --tlsv1.2 -fsSLo install.sh https://raw.githubusercontent.com/qualquer-de-tudo/openpanel-community/v0.6.0-beta.1/install.sh
sudo bash install.sh --private
```

Abra localmente `http://127.0.0.1:8080/qopenpanel/login`. Para acessar sua VPS, execute no seu computador:

```bash
ssh -L 8080:127.0.0.1:8080 usuario@IP_DO_SERVIDOR
```

Crie sua conta pelo túnel antes de publicar o domínio. Não há senha padrão. Para domínio e HTTPS automático, siga [o guia de instalação](docs/INSTALL.md). O painel utiliza PHP-FPM e Caddy; a porta FastCGI não é publicada.

## O que já funciona

- Dashboard responsivo, catálogo pesquisável e navegação via React/Inertia.
- Autenticação, proteção CSRF, limitação de tentativas e encerramento do setup após a primeira conta.
- Workspaces isolados por usuário, com validação de cotas de projetos, bancos e reservas de disco.
- Configurações planejadas para sites, Docker Compose, bancos, runtimes, e-mail, SSL, proxy, segurança, cron e integrações.
- Contas adicionais via `php artisan panel:account`, com senha solicitada no terminal.
- Imagens Docker, persistência de dados e workflow de testes/build.

## MCP para IAs

O endpoint MCP permite descoberta, gestão de configurações, cofre de credenciais por referências opacas, snapshots e restauração de configurações, planos de deploy e um broker de versionamento GitHub. Concessões têm escopos, validade e revogação; o modelo não recebe os valores do cofre. Consulte [conexão, comandos e limites de segurança](docs/MCP.md). A demo estática não oferece acesso MCP.

## Templates da comunidade

Instale projetos iniciais por versão exata, exporte arquivos com lockfile, atualize e restaure versões preservando o histórico. O catálogo inclui site estático e React/Vite. [Criar e contribuir com templates](docs/TEMPLATES.md).

## Provedores

O mesmo Compose pode ser utilizado em máquinas Linux com Docker na AWS EC2/Lightsail, Azure Virtual Machines, DigitalOcean Droplets, Google Compute Engine, Hetzner, OVHcloud, Vultr, Linode/Akamai, Oracle Cloud e servidores próprios. O [guia por provedor](docs/PROVIDERS.md) explica rede e acesso. Apenas a instalação em Docker Linux local foi validada; não há certificação de deploy em cada nuvem.

O software não cobra licença. VPS, domínio, armazenamento, tráfego e serviços externos são cobrados conforme o provedor. Não prometemos infraestrutura gratuita.

## Desenvolvimento do pacote

Requer PHP compatível com Laravel 13, Composer, Node 24 e extensões SQLite/ZIP. Não há aplicação Laravel nem `.env.example` na raiz: o Testbench fornece o ambiente dos testes.

```bash
composer install
npm ci
composer test
composer templates:check
node --test tests/Unit/*.test.mjs
npm run build:package
npm run build:demo
```

Versione os assets de `resources/dist` ao alterar a interface. Para prévia da demo: `npm run preview:demo`. Para testar o servidor completo: `bash install.sh --private` em uma máquina com as dependências disponíveis ou `sudo bash install.sh --private` num servidor Ubuntu/Debian novo.

## Licença e projeto

[MIT](LICENSE): uso, modificação e redistribuição permitidos nos termos da licença. Este é um projeto comunitário independente, sem afiliação com aaPanel ou outros projetos de nome semelhante. Dependências mantêm suas próprias licenças.
