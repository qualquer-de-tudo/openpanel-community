# Instalar em VPS ou servidor Linux

> **0.6.0-beta.1:** o produto agora é um pacote Composer. Comece pelo [guia do módulo e do novo instalador](MODULE.md), que explica Laravel em `vendor`, identidade inicial, IP, domínio e importação da beta 0.4. Para modo privado, o fluxo abaixo continua disponível.

## Requisitos

Use uma VM dedicada de avaliação, Ubuntu 24.04 LTS ou Debian com Docker Engine e o plugin Compose. Ponto de partida sugerido para construir a imagem: 2 vCPU, 2 GB RAM e 20 GB de disco; este é um dimensionamento inicial, não um benchmark. A carga futura dos serviços gerenciados não está incluída. A receita de domínio exige Compose >= 2.24.4 por usar `!override`.

Instale o Docker pelo [guia oficial para Ubuntu](https://docs.docker.com/engine/install/ubuntu/) ou [Debian](https://docs.docker.com/engine/install/debian/), incluindo `docker-compose-plugin`. O novo instalador verifica e instala dependências por apt/repositório oficial quando necessário. Comece por [BOOTSTRAP.md](BOOTSTRAP.md); a instalação manual do Docker abaixo é uma alternativa.

Verifique:

```bash
docker version
docker compose version
git --version
```

Os comandos abaixo pressupõem permissão para usar Docker. Caso seu operador exija, utilize `sudo docker`. Acesso ao socket Docker equivale a privilégios elevados no host.

## Primeiro acesso por IP, sem exposição pública

```bash
curl --proto '=https' --tlsv1.2 -fsSLo install.sh https://raw.githubusercontent.com/qualquer-de-tudo/openpanel-community/v0.6.0-beta.1/install.sh
sudo bash install.sh --private
```

O painel fica limitado a `127.0.0.1:8080` no servidor. No computador do administrador:

```bash
ssh -L 8080:127.0.0.1:8080 usuario@IP_DO_SERVIDOR
```

Abra `http://127.0.0.1:8080/qopenpanel/login` no computador e crie a primeira conta. A instalação vazia permite o cadastro inicial a quem acessá-la; conclua este passo antes de abrir o serviço à Internet. Também é possível operar permanentemente por túnel, sem domínio.

Para acesso direto ao IP em rede privada confiável, execute `PANEL_BIND=0.0.0.0 PANEL_URL=http://IP_DO_SERVIDOR:8080 docker compose up -d` e restrinja a porta 8080 na rede. Esse modo é HTTP e não deve transmitir credenciais pela Internet pública.

## Domínio e HTTPS

1. Aponte um registro A para o IP público do servidor. Só configure AAAA se o IPv6 também alcançar o servidor.
2. Libere TCP 80 e 443 no firewall do provedor e da rede. Restrinja SSH ao IP do administrador. Não publique a porta FastCGI 9000. O override remove a publicação da porta 8080.
3. Copie e edite a configuração:

```bash
cp deploy.env.example deploy.env
nano deploy.env
# PANEL_DOMAIN=painel.seudominio.com
```

4. Inicie a configuração de domínio:

```bash
docker compose --env-file deploy.env -f compose.yaml -f compose.production.yaml config --quiet
docker compose --env-file deploy.env -f compose.yaml -f compose.production.yaml up -d --build
```

O Caddy solicita e renova HTTPS automaticamente quando DNS e conectividade estão corretos. A chave do Laravel e o banco permanecem em `panel-data`; certificados ficam em `caddy-data`. Veja a [documentação de HTTPS do Caddy](https://caddyserver.com/docs/automatic-https).

Acesse `https://painel.seudominio.com/qopenpanel`. No primeiro teste, use DNS direto, sem proxy de CDN. Se usar Cloudflare depois, mantenha TLS até a origem; esta receita não implementa DNS-01 pela API Cloudflare.

Portas publicadas pelo Docker podem contornar regras simples do UFW; use também o firewall do provedor e revise o [modelo de rede do Docker](https://docs.docker.com/engine/install/ubuntu/#firewall-limitations).

## Contas de hospedagem

```bash
docker compose exec panel php artisan panel:account cliente@example.com \
  --name="Cliente" --disk=20480 --memory=2048 --projects=10 --databases=5
```

Use os mesmos argumentos `--env-file` e `-f` da instalação se estiver no modo domínio. A senha é solicitada sem eco. Disco e quantidades são reservas validadas; CPU, RAM e espaço físico ainda não são impostos pelo host. Veja [o escopo](STATUS.md).

## Operação

```bash
# Use sempre o mesmo conjunto de arquivos Compose da instalação.
docker compose ps
docker compose logs --tail=100 panel web
docker compose exec panel php artisan migrate:status
```

Para atualizar, primeiro faça backup, depois `git pull --ff-only` e execute novamente `up -d --build` com os mesmos arquivos. Revise notas de versão antes de atualizar. Uma versão antiga do código pode não ser compatível com um banco já migrado; restaure também o backup correspondente ao fazer rollback.

## Backup e recuperação

No modo domínio, substitua o comando `docker compose` pelo comando com `--env-file deploy.env -f compose.yaml -f compose.production.yaml` em cada linha. Agende uma janela sem gravações:

```bash
mkdir -p backups
chmod 700 backups
docker compose stop web panel
docker compose run --rm --no-deps --entrypoint sh panel -c 'tar -C /data -czf - .' > "backups/panel-$(date +%Y%m%d-%H%M%S).tar.gz"
docker compose start panel web
chmod 600 backups/*.tar.gz
```

Guarde os arquivos criptografados fora da VPS: contêm o banco e a chave da aplicação. Para recuperação, em uma instalação parada e com backup verificado:

```bash
# Restaura o backup escolhido, sobrescrevendo banco e chave do volume de destino.
docker compose stop web panel
cat backups/SEU_BACKUP.tar.gz | docker compose run --rm -T --no-deps --entrypoint sh panel -c 'tar -C /data -xzf -'
docker compose start panel web
```

Preserve também `deploy.env` e uma cópia protegida dos volumes do Caddy conforme sua política de recuperação. `docker compose down` preserva volumes; `down -v` apaga os dados. Teste a restauração em uma VM separada.

## Problemas comuns

- **HTTPS não emite:** confira DNS A/AAAA, relógio, portas 80/443, logs do Caddy e serviços já usando essas portas.
- **502 ao iniciar:** aguarde migrations e confira logs de `panel`; o PHP-FPM deve estar disponível na rede interna.
- **Erro de permissão no SQLite:** o volume da aplicação deve ser gravável pelo UID 1000. Evite montar bancos de outro proprietário sem ajustar permissões.
- **Cookie/CSRF em domínio:** confira que a URL usa HTTPS e que você iniciou com o override de produção. Não reutilize cookies de outro endereço.
- **Serviço continua planejado:** esperado nesta beta; a instalação do painel não provisiona os serviços do catálogo.

## Validação desta distribuição

Builds frontend, demo e imagem Docker são verificados localmente e pelo CI. O funcionamento no host Linux do Docker foi testado; os provedores listados não foram provisionados automaticamente nem homologados individualmente.
