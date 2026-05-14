<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'env' => Env::get('APP_ENV', 'local'),
    'debug' => filter_var(Env::get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
    'url' => Env::get('APP_URL', 'http://localhost:8000'),
    'timezone' => Env::get('APP_TIMEZONE', 'Asia/Jakarta'),

    'storage_path' => Env::get('STORAGE_PATH', 'storage'),
    'export_path' => Env::get('EXPORT_PATH', 'storage/exports'),
    'upload_path' => Env::get('UPLOAD_PATH', 'storage/uploads'),
    'max_upload_mb' => (int) (Env::get('MAX_UPLOAD_MB', '5') ?? '5'),
];
