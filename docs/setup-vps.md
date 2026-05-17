# Setup VPS — TCC-II UNIVILLE

> Comparação GitHub Actions vs Jenkins para CI/CD em PHP
> VPS Hostinger — Ubuntu 25.10 — IP: `76.13.112.86`

---

## Ambiente

| Ferramenta | Versão     | Status |
|------------|------------|--------|
| PHP        | 8.4.11     | ✅     |
| Composer   | 2.9.5      | ✅     |
| Node.js    | 20.20.0    | ✅     |
| PM2        | 6.0.14     | ✅     |
| Docker     | 29.2.0     | ✅     |
| Git        | 2.51.0     | ✅     |

---

## 1. Instalação do PHP 8.4

Ubuntu 25.10 (Questing) não suporta o PPA `ondrej/php`. PHP 8.4 foi instalado direto dos repositórios oficiais:

```bash
apt update && apt install -y php php-sqlite3 php-mbstring php-cli php-curl php8.4-xml
```

---

## 2. Composer

O Composer não estava no PATH após a instalação. Corrigido manualmente:

```bash
cp /root/composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer
```

---

## 3. Deploy da API

### Clone e dependências

```bash
mkdir -p /var/www && cd /var/www
git clone https://github.com/richard-melo/tcc-api-php.git
cd tcc-api-php
composer install --no-dev --no-security-blocking
```

> `--no-security-blocking` necessário por advisory de segurança no `firebase/php-jwt` (aceitável para TCC).

### JWT Secret

```bash
openssl rand -hex 32
# Resultado salvo no ecosystem.config.js
```

Secret gerado: `dde08052bc969e2f320f3e59a8411043145f34e9beb530247ff2ed8cf2d6f67f`

### Iniciar com PM2

```bash
pm2 start /var/www/tcc-api-php/ecosystem.config.js
pm2 save
pm2 startup
```

API disponível em: `http://76.13.112.86:8000`

---

## 4. Correções no repositório

### 4.1 — `src/Config/Database.php` ausente

O arquivo `config/database.php` existia com namespace `App\Config\Database`, mas o autoload PSR-4 mapeia `App\` → `src/`. O arquivo foi movido para `src/Config/Database.php` e commitado.

### 4.2 — Diretórios em minúsculo

No macOS o git não detecta rename de caixa. Os diretórios foram renomeados via two-step `git mv`:

| Antes         | Depois        |
|---------------|---------------|
| `controllers` | `Controllers` |
| `middleware`  | `Middleware`  |
| `models`      | `Models`      |
| `repositories`| `Repositories`|
| `services`    | `Services`    |

Linux é case-sensitive — sem esse ajuste o autoload falha em produção.

---

## 5. Jenkins via Docker Compose

### Variáveis de ambiente (`~/.env`)

```env
JENKINS_HOME_PATH=/var/jenkins_home
JENKINS_AGENT_SSH_PUBLIC_KEY=<chave pública gerada em ~/.ssh/jenkins_agent.pub>
```

### Subir Jenkins

```bash
docker compose --env-file ~/.env up -d
```

### Senha inicial

```bash
docker exec jenkins_sandbox cat /var/jenkins_home/secrets/initialAdminPassword
```

### Acesso

```
http://76.13.112.86:8080
```

Plugins instalados: sugeridos pelo wizard (Git, Pipeline, JUnit, etc.)

---

## 6. Próximos passos

- [ ] Criar Job Pipeline no Jenkins apontando para `Jenkinsfile` do repositório
- [ ] Configurar webhook GitHub → Jenkins (`http://76.13.112.86:8080/github-webhook/`)
- [ ] Configurar GitHub Actions (`.github/workflows/ci.yml`)
- [ ] Rodar testes com PHPUnit e comparar métricas entre as duas ferramentas
