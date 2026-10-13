<?php
declare(strict_types=1);
$config = [
    'db_host' => getenv('DB_HOST') ?: 'localhost',
    'db_port' => (int)(getenv('DB_PORT') ?: 3306),
    'db_name' => getenv('DB_NAME') ?: 'system404_monop',
    'db_user' => getenv('DB_USER') ?: 'system404_monop',
    'db_password' => getenv('DB_PASSWORD') ?: '',
    'base_url' => rtrim(getenv('APP_URL') ?: 'https://www.monopoly-torg.ru', '/'),
    'base_path' => rtrim(getenv('APP_BASE_PATH') ?: '', '/'),
    'timezone' => 'Europe/Moscow',
    'mail_transport' => getenv('MAIL_TRANSPORT') ?: 'mail',
    'mail_to' => 'monopoly@list.ru',
    'mail_from' => getenv('MAIL_FROM') ?: 'monopoly@monopoly-torg.ru',
    'smtp_host' => getenv('SMTP_HOST') ?: '',
    'smtp_port' => (int)(getenv('SMTP_PORT') ?: 587),
    'smtp_encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls',
    'smtp_user' => getenv('SMTP_USER') ?: '',
    'smtp_password' => getenv('SMTP_PASSWORD') ?: '',
    'session_secure' => true,
    'max_upload_bytes' => 10 * 1024 * 1024,
];
$local = __DIR__ . '/config.local.php';
if (is_file($local)) $config = array_replace($config, require $local);
return $config;
