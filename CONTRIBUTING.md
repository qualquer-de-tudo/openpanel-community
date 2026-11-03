# Contribuir

Obrigado por ajudar o qopenpanel-community. Abra uma issue descrevendo o problema ou propondo a mudança antes de refatorações grandes.

1. Faça um fork e crie uma branch.
2. Instale dependências com `composer install` e `npm ci`.
3. Copie `.env.example` para `.env`, gere a chave e execute migrations.
4. Execute `php artisan test`, `npm run build` e `npm run build:demo`.
5. Envie um pull request com comportamento alterado e validação realizada.

Não inclua tokens, chaves privadas, `.env`, dumps de clientes ou dados pessoais. Receitas que executem ações no host precisam de testes em Linux, validação de entradas e uma descrição do modelo de permissões. Não marque funcionalidades planejadas como operacionais.

Suas contribuições são distribuídas sob a licença MIT do projeto.
