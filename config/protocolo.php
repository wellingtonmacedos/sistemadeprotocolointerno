<?php

return [
    // Limite de upload de anexos (KB). Configurável por ambiente.
    // Atenção: deve ser compatível com upload_max_filesize e post_max_size do PHP.
    'max_upload_kb' => env('PROTOCOLO_MAX_UPLOAD_KB', 10240),

    // Diretório privado dos anexos (relativo ao disco "local").
    'attachments_path' => env('PROTOCOLO_ATTACHMENTS_PATH', 'attachments'),

    // Diretório privado dos backups.
    'backups_path' => env('PROTOCOLO_BACKUPS_PATH', 'backups'),

    /*
    |--------------------------------------------------------------------------
    | Chave de Criptografia dos Backups
    |--------------------------------------------------------------------------
    |
    | Chave dedicada (AES-256-GCM) usada para criptografar os arquivos de
    | backup. Quando não informada, a chave é derivada da APP_KEY — o que
    | mantém os backups antigos legíveis, mas amarra o backup à APP_KEY.
    | Guarde este valor em local seguro: sem ele os backups são irrecuperáveis.
    |
    */
    'backup_key' => env('BACKUP_ENCRYPTION_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Super Administrador
    |--------------------------------------------------------------------------
    |
    | Credenciais usadas somente pelo SuperAdminSeeder. Ficam aqui (e não via
    | env() dentro do seeder) para continuarem funcionando com config:cache,
    | caso em que o arquivo .env deixa de ser lido em tempo de execução.
    |
    */
    'super_admin' => [
        'email' => env('SUPER_ADMIN_EMAIL', 'superadmin@protocolo.local'),
        'password' => env('SUPER_ADMIN_PASSWORD', 'Admin@12345'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Proxies Confiáveis
    |--------------------------------------------------------------------------
    |
    | Aceita "*" (confia em qualquer proxy) ou uma lista de IPs separada por
    | vírgula. Necessário quando a aplicação roda atrás de Nginx/balanceador,
    | para que o HTTPS e o IP real do cliente sejam detectados corretamente.
    |
    */
    'trusted_proxies' => env('TRUSTED_PROXIES'),
];
