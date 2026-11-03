# Instalação por provedor

Em todos os casos, a aplicação é instalada dentro de uma VM Linux com Docker seguindo [INSTALL.md](INSTALL.md). Não precisa instalar PHP, Node ou banco de dados diretamente no host. Não forneça suas credenciais de nuvem ao painel.

## Rede comum

Para o primeiro acesso, basta SSH, restrito ao IP do administrador, e túnel local. Para domínio público, adicione HTTP 80 e HTTPS 443. Não exponha 9000 nem bancos internos. Prefira IP estável ou reservado e DNS A/AAAA coerente. Os recursos de hardware e os custos são escolhidos pelo proprietário da conta.

| Provedor | Recurso e preparação | Controle de rede |
| --- | --- | --- |
| AWS | EC2 com Ubuntu e chave SSH; Lightsail também pode executar a receita Linux | Security Group no EC2 ou firewall da instância Lightsail; IP estável para o DNS |
| Azure | Virtual Machine Linux, autenticação por chave SSH e IP público | Network Security Group da VM/sub-rede |
| DigitalOcean | Droplet Linux com chave SSH e IP acessível | Cloud Firewall vinculado ao Droplet |
| Google Cloud | Compute Engine com VM Linux e acesso SSH | Regras VPC associadas à VM, tags ou service account conforme configuração |
| Hetzner | Cloud Server Linux com Primary IP e chave SSH | Cloud Firewall vinculado ao servidor |
| OVHcloud | VPS ou instância Public Cloud Linux | Firewall de rede do produto e regras do sistema operacional |
| Vultr | Cloud Compute Linux | Firewall Group da instância |
| Akamai/Linode | Compute Instance Linux | Cloud Firewall associado à instância |
| Oracle Cloud | Compute Instance Linux com subnet e acesso público configurados | Network Security Group ou Security Lists e firewall do sistema |
| Servidor dedicado/local | Linux com Docker e acesso SSH | Firewall do host/roteador; NAT de portas apenas quando necessário |

Cada produto tem características próprias. Esta tabela orienta onde preparar a VM e a rede; não afirma compatibilidade certificada. AWS/Azure/Google Cloud podem exigir cotas, faturamento e disponibilidade regional antes de criar uma instância. Não foram criados recursos pagos para validar estes guias.

## AWS EC2 ou Lightsail

Crie uma instância Ubuntu, adicione uma chave e conecte via SSH usando o usuário indicado pela imagem (em imagens Ubuntu normalmente `ubuntu`). Restrinja SSH na rede. Faça a instalação comum pelo túnel. Para publicar domínio, reserve um endereço estável e adicione HTTP/HTTPS nas regras de entrada. Referências: [Security Groups do EC2](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/security-group-rules-reference.html) e [documentação Lightsail](https://docs.aws.amazon.com/lightsail/).

## Azure

Crie uma VM Ubuntu com chave SSH, IP público e regras NSG adequadas. Use o usuário administrador definido na criação. Após a instalação pelo túnel, habilite domínio e HTTPS seguindo o guia comum. Consulte o [guia oficial de VM Linux](https://learn.microsoft.com/azure/virtual-machines/linux/quick-create-portal).

## DigitalOcean

Crie um Droplet Linux, associe sua chave SSH e um Cloud Firewall. Instale por túnel, configure DNS e libere 80/443 ao ativar o domínio. Consulte [conexão OpenSSH](https://docs.digitalocean.com/products/droplets/how-to/connect-with-ssh/openssh/).

## Google Cloud

Crie uma VM no Compute Engine com Linux e configure o acesso SSH. Ao publicar, associe as regras HTTP/HTTPS à VM correta e mantenha um IP estável. Consulte [criação de instâncias](https://docs.cloud.google.com/compute/docs/instances/create-start-instance).

## Hetzner e demais VPS

Na Hetzner, crie o servidor com sua chave e IP primário: [guia oficial](https://docs.hetzner.com/cloud/servers/getting-started/creating-a-server/). Nos demais provedores, escolha uma VM Linux que atenda aos requisitos e siga a instalação comum. Guias de referência: [OVHcloud](https://help.ovhcloud.com/), [Vultr](https://docs.vultr.com/), [Akamai Cloud](https://techdocs.akamai.com/cloud-computing/docs), [Oracle Compute](https://docs.oracle.com/en-us/iaas/Content/Compute/home.htm).

## Custos

A licença MIT do projeto é gratuita. Nenhum guia depende de plano gratuito de nuvem. Confira cobranças de instâncias, IPv4, discos, snapshots, tráfego, domínio e eventuais serviços externos diretamente no seu provedor antes de criar recursos.
