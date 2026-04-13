module.exports = {
  apps: [
    {
      name: 'tcc-api-php',
      script: 'php',
      args: '-S 0.0.0.0:8000 -t public/',
      cwd: '/var/www/tcc-api-php',
      interpreter: 'none',
      watch: false,
      autorestart: true,
      restart_delay: 3000,
      max_restarts: 10,
      env: {
        APP_ENV: 'production',
        DB_PATH: '/var/www/tcc-api-php/database/expenses.db',
        JWT_SECRET: 'TROQUE_ESTA_CHAVE_POR_UMA_SEGURA',
      },
    },
  ],
};
