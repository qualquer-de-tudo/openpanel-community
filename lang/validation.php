<?php

return [
    'required' => __('O campo :attribute é obrigatório.'),
    'required_if' => __('O campo :attribute é obrigatório quando :other é :value.'),
    'required_unless' => __('O campo :attribute é obrigatório, exceto quando :other é :value.'),
    'string' => __('O campo :attribute deve ser um texto.'),
    'email' => __('Informe um e-mail válido.'),
    'confirmed' => __('A confirmação de :attribute não confere.'),
    'accepted' => __('O campo :attribute deve ser aceito.'),
    'unique' => __('Este :attribute já está em uso.'),
    'in' => __('Escolha uma opção válida para :attribute.'),
    'integer' => __('O campo :attribute deve ser um número inteiro.'),
    'regex' => __('O formato de :attribute é inválido.'),
    'url' => __('Informe uma URL HTTPS válida para :attribute.'),
    'array' => __('O campo :attribute deve ser uma lista.'),
    'uuid' => __('O campo :attribute deve ser um UUID válido.'),
    'min' => ['string' => __('O campo :attribute deve ter pelo menos :min caracteres.'), 'numeric' => __('O campo :attribute deve ser no mínimo :min.')],
    'max' => ['string' => __('O campo :attribute deve ter no máximo :max caracteres.'), 'numeric' => __('O campo :attribute deve ser no máximo :max.')],
    'attributes' => [
        'name' => __('Nome'), 'email' => __('E-mail'), 'password' => __('Senha'),
        'domain' => __('Domínio'), 'repository' => __('Repositório'), 'kind' => __('Categoria'),
        'engine' => __('Stack'), 'disk_mb' => __('Reserva de disco'), 'action' => __('Ação'),
        'resource_id' => __('PROJETO / RECURSO'), 'reference' => __('Referência'),
        'expected_revision' => __('Revisão esperada'), 'variable' => __('Variável'),
        'acknowledge' => __('Autorização'), 'channel' => __('Canal de versões'), 'version' => __('Versão'),
    ],
];
