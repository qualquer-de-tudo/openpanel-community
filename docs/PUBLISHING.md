# Publicação e demo

Repositório: https://github.com/qualquer-de-tudo/openpanel-community

Demo esperada após deployment do Pages: https://qualquer-de-tudo.github.io/openpanel-community/

## GitHub Pages

Em Settings > Pages, selecione GitHub Actions como fonte. O workflow `Demo on GitHub Pages` compila `dist-demo` e publica com as ações oficiais do GitHub. O endereço efetivo aparece no deployment `github-pages`. Siga a [documentação oficial de workflows Pages](https://docs.github.com/en/pages/getting-started-with-github-pages/using-custom-workflows-with-github-pages).

A demo é uma aplicação estática de navegação, sem Laravel, banco ou credenciais. Dados fictícios são marcados na interface. Formulários abrem para exploração, mas não enviam gravações. A aplicação administrativa real deve ser instalada na sua infraestrutura; GitHub Pages não executa PHP.

Para publicar a mesma demo em outro servidor de arquivos estáticos:

```bash
npm ci
npm run build:demo
# Publique o conteúdo de dist-demo, usando index.html como entrada.
```

Os assets têm caminhos relativos para funcionar em subdiretórios. A demo não usa rotas de servidor: a navegação acontece dentro da mesma página.

## Releases

Marque uma versão apenas depois de testar CI, builds e documentação. Inclua notas com o escopo e limitações. Não distribua `.env`, `deploy.env`, bancos SQLite, dados de clientes ou chaves. As dependências são reproduzidas por `composer.lock` e `package-lock.json`.

## Gratuidade

Código sob MIT e demo estática em repositório público; a disponibilidade do Pages segue as regras e limites do GitHub. Infraestrutura privada e serviços de terceiros têm custos próprios.
