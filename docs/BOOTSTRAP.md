# Instalador automático do OpenPanel Community

O repositório contém o pacote, não uma aplicação Laravel. `install.sh` verifica as dependências do servidor; o Dockerfile baixa um Laravel limpo para a imagem e instala `qualquer-de-tudo/openpanel-community` em `vendor`. O host não precisa de PHP, Composer, Node ou Laravel.

## Instalação em um servidor novo

Baixe `install.sh` da release e execute com Bash. Se curl já estiver disponível:

```bash
curl --proto '=https' --tlsv1.2 -fsSLo install.sh https://raw.githubusercontent.com/qualquer-de-tudo/openpanel-community/v0.6.0-beta.1/install.sh
sudo bash install.sh --private
```

Também pode transferir o arquivo por SCP ou baixá-lo pelo navegador. Depois de iniciado, o instalador verifica e instala Git, curl, certificados CA, Docker Engine, Compose >=2.24.4 e Buildx. Quando necessário, usa apt e o repositório oficial assinado do Docker; não executa scripts remotos de instalação. O pacote do painel é baixado da release indicada e o build prepara Laravel 13/PHP 8.5 e os serviços do painel.

Instalação automática das dependências: Ubuntu 22.04, 24.04, 26.04 e 26.10; Debian 12 e 13, em amd64/arm64. A instalação dos pacotes foi testada em containers limpos Ubuntu 24.04 e Debian 13. Esses testes não equivalem à homologação de uma VM em cada provedor nem à execução de um daemon Docker dentro desses containers.

Para inspecionar antes de modificar o servidor:

```bash
bash install.sh --check
```

O modo `--check` é somente leitura. Ferramentas já compatíveis são reutilizadas. Em outras distribuições, o instalador pode prosseguir quando Docker, Compose, Buildx, Git e curl já estiverem funcionando. Se precisar instalar pacotes em sistema não suportado, ele para com uma mensagem.

O script inicia Docker via systemd ou serviço quando necessário. Se o usuário não tiver acesso ao daemon, usa root/sudo para as chamadas Docker, sem adicionar automaticamente usuários ao grupo Docker. Um servidor com pacotes conflitantes, como `docker.io`, `podman-docker`, `containerd` ou `runc`, é preservado: o instalador para antes de substituir a instalação existente. Se todas as ferramentas já funcionam, não troca seus pacotes.

## Primeira conta, IP e domínio

O modo privado publica somente em loopback. No computador do administrador:

```bash
ssh -L 8080:127.0.0.1:8080 usuario@IP_DO_SERVIDOR
```

Abra `http://127.0.0.1:8080/qopenpanel/login` e crie a primeira conta. Nome e e-mail podem ser preparados com `--name` e `--email`. Não existe senha padrão. Para criar a conta pelo terminal e depois liberar IP:

```bash
sudo bash install.sh --ip 203.0.113.10 --email admin@example.com --name "Administrador"
```

Substitua o IP de exemplo. A senha é solicitada sem eco antes da publicação. Acesso por IP usa HTTP; restrinja a porta 8080 à rede administrativa ou mantenha o túnel até configurar HTTPS.

Para usar domínio apontado ao servidor, desde a instalação ou posteriormente:

```bash
cd openpanel-community
sudo bash install.sh --domain painel.example.com
```

Use o mesmo checkout e os mesmos volumes. O fluxo preserva a conta existente. A/AAAA precisam alcançar a máquina e portas 80/443 devem estar disponíveis. Caddy solicita e renova certificados automaticamente. O instalador sobe PHP-FPM, Caddy e o armazenamento SQLite do painel; os serviços gerenciados do catálogo continuam dependendo do executor Linux.

## Atualizar instalações anteriores

Não clone outro diretório para atualizar uma instalação existente: o nome do projeto Compose pode mudar e selecionar volumes diferentes. Preserve o checkout, `deploy.env`, volumes e backups. Atualize o remote para `https://github.com/qualquer-de-tudo/openpanel-community.git` e use o mesmo comando Compose da instalação.

Na imagem independente, o pacote é reinstalado durante o build, preservando os volumes. No Laravel hospedeiro que usava o nome Composer anterior, altere a dependência de forma conjunta:

```bash
composer remove qualquer-de-tudo/qopenpanel-community --no-update
composer config repositories.qopenpanel vcs https://github.com/qualquer-de-tudo/openpanel-community.git
composer require "qualquer-de-tudo/openpanel-community:^0.6@beta" --with-all-dependencies
php artisan qopenpanel:install --force
php artisan config:clear
php artisan route:clear
```

Faça backup antes. Namespace, tabelas e caminhos internos `qopenpanel` continuam estáveis. A base 0.4 requer a importação explícita documentada em [MODULE.md](MODULE.md). Em um Laravel já existente, o instalador de VPS não é necessário: use somente o Composer.

Referências do bootstrap: [Docker no Ubuntu](https://docs.docker.com/engine/install/ubuntu/) e [Docker no Debian](https://docs.docker.com/engine/install/debian/).
