<?php

return [
    'required' => 'O campo :attribute é obrigatório.',
    'string' => 'O campo :attribute deve ser um texto.',
    'email' => 'Informe um e-mail válido.',
    'confirmed' => 'A confirmação de :attribute não confere.',
    'unique' => 'Este :attribute já está em uso.',
    'in' => 'Escolha uma opção válida para :attribute.',
    'integer' => 'O campo :attribute deve ser um número inteiro.',
    'regex' => 'O formato de :attribute é inválido.',
    'url' => 'Informe uma URL HTTPS válida para :attribute.',
    'min' => ['string' => 'O campo :attribute deve ter pelo menos :min caracteres.', 'numeric' => 'O campo :attribute deve ser no mínimo :min.'],
    'max' => ['string' => 'O campo :attribute deve ter no máximo :max caracteres.', 'numeric' => 'O campo :attribute deve ser no máximo :max.'],
    'attributes' => ['name' => 'nome', 'email' => 'e-mail', 'password' => 'senha', 'domain' => 'domínio', 'repository' => 'repositório', 'kind' => 'categoria', 'engine' => 'stack', 'disk_mb' => 'reserva de disco'],
];
