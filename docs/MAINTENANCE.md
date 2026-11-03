# Instalação em um comando, atualizações e segurança

## Instalar sem clonar o repositório manualmente

Em uma sessão SSH de um servidor Ubuntu/Debian suportado:

```bash
curl --proto '=https' --tlsv1.2 -fsSL https://raw.githubusercontent.com/qualquer-de-tudo/openpanel-community/main/install.sh | sudo bash -s -- --private
```

O script funciona pelo stdin: instala dependências ausentes, baixa a release do produto automaticamente, cria Laravel na imagem e instala o pacote em `vendor`. Não exige PHP, Composer, Node ou Laravel no host. Curl é o transporte para buscar o primeiro script; se não estiver disponível, transfira esse arquivo por SCP ou use wget. As demais dependências são verificadas pelo próprio instalador.

O modo privado mantém a porta em loopback para criar a primeira conta pelo túnel SSH. Para criar a conta pelo terminal e publicar um domínio já apontado, substitua `--private` por `--domain painel.example.com`. Para IP inicial: `--ip SEU_IP`. Em execução pelo pipe, perguntas interativas usam `/dev/tty`; sem terminal, use `--private`. O nome/e-mail podem ser informados com `--name` e `--email`; não há senha padrão nem senha em argumentos.

O instalador preserva um checkout existente, exige que arquivos versionados estejam limpos antes de trocar de release e mantém projeto/volumes Compose. Não remove alterações locais. Instalação automática de dependências continua limitada às versões Ubuntu/Debian documentadas em [BOOTSTRAP.md](BOOTSTRAP.md).

## Agente de manutenção

Em Linux com systemd e Docker rootful, o instalador registra um agente restrito. Docker Desktop, instalações rootless ou sistemas sem systemd podem executar o painel, mas não recebem manutenção automática do host. A integração pronta desta beta gerencia a instalação independente com serviços Compose `panel`/`web` e volume `/data`; não gerencia a infraestrutura arbitrária de um Laravel hospedeiro.

O agente usa Python 3 da distribuição e um timer systemd. O painel não recebe socket Docker, SSH do host, shell livre ou arquivos de configuração do agente. A comunicação é uma fila local com somente duas ações: atualizar para uma release oficial e executar verificação de segurança.

Arquivos e permissões:

| Local | Finalidade |
| --- | --- |
| `/usr/local/lib/openpanel/host-agent.py` | Código instalado pelo administrador, sem permissão de escrita para a aplicação |
| `/etc/openpanel/maintenance.json` | Configuração root, modo 600 |
| `/var/lib/openpanel/control/requests` | Pedidos atômicos escritos pelo UID 1000 do painel |
| `/var/lib/openpanel/control/reports` | Resultados sanitizados escritos por root, leitura pelo grupo 1000 |
| `/var/lib/openpanel/backups` | Backups privados, diretório root 700 |
| `/var/lib/openpanel/releases` | Releases oficiais baixadas pelo agente |

O timer processa a fila aproximadamente a cada 15 segundos. Há exclusão mútua, limite de tamanho, rejeição de symlinks/FIFOs, validação estrita dos campos, expiração dos pedidos e proteção contra repetição. Uma tarefa interrompida não é executada de novo automaticamente. Um processo web comprometido ainda pode causar indisponibilidade ou tentar as ações permitidas; isso não é um isolamento completo contra comprometimento de toda a aplicação. O broker limita o que pode ser enviado ao host.

## Quem pode atualizar

A primeira conta de uma instalação nova é operadora. Contas de hospedagem adicionais não recebem essa permissão. Instalações anteriores não promovem usuários silenciosamente: o administrador designa a conta no terminal:

```bash
docker compose exec panel php artisan qopenpanel:operator admin@example.com
```

Use os mesmos argumentos Compose da instalação; para domínio, acrescente `--env-file deploy.env -f compose.yaml -f compose.production.yaml -f compose.maintenance.yaml`. Para revogar, use `--revoke`. Em um Laravel hospedeiro, execute o Artisan do aplicativo. Essa designação não instala um agente para uma infraestrutura personalizada.

Somente operadores veem **Manutenção do servidor**. A atualização exige senha atual, seleção da versão e autorização explícita da janela de manutenção. Contextos MCP são recusados; as IAs não recebem uma ferramenta de atualização do host. O agente confere novamente no banco se o pedido, o commit e a permissão do operador continuam válidos antes de agir.

## Atualizar ou fazer upgrade

1. Abra **Manutenção do servidor** e **Buscar versões**. O canal padrão é estável; versões alpha/beta/rc exigem selecionar o canal que inclui betas.
2. Leia as notas, escolha uma versão posterior, confirme a senha e autorize a manutenção.
3. O pedido entra no histórico. Use **Atualizar status** para acompanhar. Haverá indisponibilidade durante a troca dos containers.

O agente verifica a release no repositório oficial, compara o commit com o escolhido no painel, exige um workflow `CI` aprovado para esse commit e verifica o checkout. Não aceita downgrade, `latest`, intervalos, repositórios ou comandos fornecidos pelo cliente. A confiança é no GitHub/repositório oficial e no CI; não há assinatura independente de releases nesta beta.

Antes de parar a versão atual, o candidato é construído. O agente preserva as imagens anteriores, interrompe o tráfego publicado, faz backup do volume e inicia o candidato sem publicar suas portas. Valida versão, migrations, banco, capacidade de abrir o cofre existente e página HTTP pela rede interna. Só então publica as portas. O nome do projeto e os volumes continuam os mesmos.

Falhas anteriores à publicação tentam restaurar banco/chave e imagens anteriores. Quando a publicação começa, não se restaura uma base antiga automaticamente: pode já haver gravações novas. Falha nessa fase, erro de restauração ou interrupção do agente exige recuperação humana, indicada por `recovery_required`. Não é uma promessa de zero downtime ou transação atômica entre Docker, banco e sistema de arquivos.

## Backups e recuperação

Backups incluem banco, chave mestra e arquivos privados. São restritos a root, mas não são criptografados automaticamente. Guarde cópias criptografadas fora do host e verifique espaço em disco antes de atualizar. Imagens e backups anteriores são preservados; não há limpeza automática nesta beta.

Para diagnóstico local:

```bash
sudo systemctl status openpanel-maintenance.timer
sudo journalctl -u openpanel-maintenance.service --since today
sudo python3 /usr/local/lib/openpanel/host-agent.py --current
sudo python3 /usr/local/lib/openpanel/host-agent.py --project
```

O agente registra códigos, não payloads com credenciais. Em recuperação, primeiro interrompa o tráfego, confira o estado dos containers, o diretório atual registrado e o backup da tarefa. Só restaure uma base antiga em uma janela sem gravações; valide o arquivo de backup e use as imagens correspondentes. Consulte também [o guia de backup](INSTALL.md#backup-e-recuperação). Não execute um build de uma versão antiga por cima de uma base já migrada sem planejar essa restauração.

## CVEs e verificações do servidor

O botão **Executar verificação** solicita uma análise de pacotes do host Ubuntu/Debian com Trivy 0.76.1 fixado por digest de imagem. O scanner recebe uma montagem somente leitura, sem socket Docker, sem capabilities e com limites de CPU, memória e processos. A base de vulnerabilidades é obtida dos repositórios OCI padrão do Trivy; a comparação acontece localmente. A função de busca de segredos não é executada.

O relatório contém CVE/advisory, pacote, versão instalada, correção informada, severidade, data e sinal de fim de suporte do sistema. As contagens são ocorrências por pacote; uma CVE pode aparecer para vários pacotes. Há limites de linhas e indicação de truncamento. Também são lidas configurações efetivas de SSH, quando `sshd` está disponível, e o indicador de reinicialização do sistema.

Ausência de relatório, erro de download, sistema não reconhecido, tarefa falha ou relatório antigo não significam servidor seguro. O painel sinaliza ausência e idade dos dados. Uma falha de verificação não apaga o último relatório válido nem o torna recente. A análise não corrige pacotes automaticamente, não cobre todas as CVEs do kernel, bibliotecas das aplicações, containers gerenciados, configuração completa do firewall, falhas de negócio ou vulnerabilidades ainda não conhecidas.

O scanner foi exercitado contra metadados de uma imagem Ubuntu de teste; isso não representa uma auditoria da VPS do usuário. As rotinas de atualização possuem testes de protocolo, autorização, preservação de backup e falhas de rollback. Esta beta ainda deve ser validada em homologação antes de uma VPS com dados importantes.

Referências: [Trivy rootfs](https://trivy.dev/docs/latest/references/configuration/cli/trivy_rootfs/), [releases do Trivy](https://github.com/aquasecurity/trivy/releases), [releases do GitHub](https://docs.github.com/en/rest/releases/releases).
