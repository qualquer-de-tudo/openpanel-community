# Traduções

A interface, as validações do Laravel, mensagens de operação e textos do MCP usam catálogos JSON versionados em `lang/`. As seis línguas iniciais são português brasileiro (`pt_BR`), inglês (`en`), francês (`fr`), espanhol (`es`), mandarim em chinês simplificado (`zh_CN`) e japonês (`ja`).

## Traduzir um texto existente

1. Abra `lang/fr.json`, por exemplo.
2. Localize a frase de origem e altere apenas seu valor. As chaves permanecem iguais em todos os idiomas.
3. Preserve parâmetros como `:name`, `:count`, `:min`, `:max` e `:version`.
4. Execute:

```bash
npm run translations:check
npm run build
npm run build:demo
```

A checagem mostra a cobertura de cada idioma e falha se faltar uma chave, houver texto vazio, parâmetros incompatíveis ou texto JSX visível sem tradução. O CI verifica as traduções antes de publicar a demo. Nomes próprios como Laravel, Docker e GitHub e dados do usuário não são traduzidos.

## Adicionar um idioma

Não é necessário alterar componentes React ou listas PHP:

```bash
npm run translations:add -- de Deutsch de-DE
```

Isso registra o idioma em `resources/data/locales.json`, cria `lang/de.json` com valores vazios e adiciona o carregador de validação do Laravel. Preencha os valores do JSON e rode `npm run translations:check`. O idioma só deve ser publicado quando a checagem passar. Um novo idioma incompleto usa português como fallback em tempo de execução, mas a publicação pelo CI é bloqueada.

O registro também aceita `direction: "rtl"` para a direção do documento. Os seis idiomas iniciais usam `ltr`. Novos idiomas RTL precisam de revisão visual adicional de layout.

## Adicionar novos textos ao produto

Adicione a chave e sua tradução em português a `lang/pt_BR.json`, depois execute:

```bash
npm run translations:sync
npm run translations:status
```

`sync` adiciona chaves ausentes com valores vazios nos outros idiomas, sem sobrescrever traduções existentes. Complete os valores e valide antes de enviar o pull request.

Em React:

```jsx
const { t, number } = useI18n();
<p>{t('Olá, :name. Vamos dar vida ao seu próximo projeto?', { name })}</p>
```

Em Laravel:

```php
__('Configuração removida.');
```

Use frases completas e parâmetros, em vez de concatenar fragmentos novos. Evite colocar texto traduzido como identificador de estado: categorias, IDs, ações MCP e valores enviados ao backend são canônicos e independentes do idioma. Não use HTML nas traduções; React escapa os valores apresentados.

## Como a preferência funciona

O seletor aparece no cabeçalho do painel e na tela de login. A preferência é persistida no navegador. Um cookie sem informações sensíveis permite ao Laravel escolher o mesmo idioma para as próximas requisições; na ausência dele, o servidor negocia `Accept-Language`. A preferência local tem prioridade sobre a configuração inicial do navegador.

A demo estática carrega os mesmos catálogos. Alterar o idioma não cria sessão administrativa nem envia dados de projeto. O botão de instalação abre o repositório público do projeto.

Na CLI, use `APP_LOCALE` ao inicializar a aplicação. Na ponte MCP Node, use `QOPENPANEL_LOCALE` (ou `LANG`):

```bash
QOPENPANEL_LOCALE=fr node bin/mcp-bridge.mjs /diretorio/privado/client.mcp-private.json
```

O MCP envia descrições, instruções e erros da ponte no idioma selecionado. Nomes de ferramentas, códigos de erro, ações e campos do protocolo permanecem estáveis para preservar a integração. Argumentos técnicos de comandos e mensagens internas de dependências podem seguir o idioma original da biblioteca; textos do produto devem passar pelos catálogos.

## Testes

```bash
npm run test:i18n
php artisan test --filter=LocalizationTest
npm run translations:check
```

Os testes verificam os seis idiomas, interpolação, fallback, negociação de idioma, preferência persistida no backend e validação traduzida. Revise traduções com falantes nativos antes de exigir terminologia especializada; estes catálogos iniciais podem evoluir por pull requests.
