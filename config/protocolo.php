<?php

return [
    // Limite de upload de anexos (KB). Configurável por ambiente.
    'max_upload_kb' => env('PROTOCOLO_MAX_UPLOAD_KB', 10240),

    // Diretório privado dos anexos (relativo ao disco "local").
    'attachments_path' => env('PROTOCOLO_ATTACHMENTS_PATH', 'attachments'),

    // Diretório privado dos backups.
    'backups_path' => env('PROTOCOLO_BACKUPS_PATH', 'backups'),
];
