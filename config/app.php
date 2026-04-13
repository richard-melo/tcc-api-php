<?php

declare(strict_types=1);

return [
    'jwt_secret'  => $_ENV['JWT_SECRET']  ?? 'change-this-secret-in-production',
    'jwt_ttl'     => (int) ($_ENV['JWT_TTL'] ?? 86400), // 24h em segundos
    'app_env'     => $_ENV['APP_ENV']     ?? 'production',
];
