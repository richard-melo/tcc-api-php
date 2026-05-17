# TCC-II — Contexto Completo do Projeto

> **Richard Luiz Melo Neto**
> Bacharelando em Engenharia de Software — UNIVILLE (Joinville, SC)
> Orientadora: Profª Rafaela Bosse Schroeder
> Ano: 2026

---

## 1. Objetivo do Trabalho

Comparar empiricamente duas plataformas de CI/CD — **GitHub Actions** e **Jenkins** — aplicadas ao mesmo projeto de software, avaliando critérios como:

- Tempo de execução do pipeline
- Facilidade de configuração
- Rastreabilidade dos resultados
- Curva de aprendizado
- Custo operacional

O artefato prático é uma **API REST de gerenciamento de gastos pessoais** desenvolvida em PHP 8.1+ puro (sem frameworks), servindo como base comum para ambos os pipelines.

---

## 2. Por que PHP Puro?

A ausência de framework (Laravel, Symfony) foi uma decisão deliberada para garantir que o **pipeline de CI/CD seja o foco real da avaliação**, eliminando ruído causado pela complexidade de frameworks. Também evidencia domínio dos fundamentos da linguagem.

---

## 3. Stack Técnica

| Tecnologia          | Versão   | Função                                   |
|---------------------|----------|------------------------------------------|
| PHP                 | 8.1+     | Linguagem principal                      |
| SQLite              | 3.x      | Banco de dados embutido (via PDO)        |
| firebase/php-jwt    | ^6.10    | Geração e validação de JWT HS256         |
| PHPUnit             | ^10.5    | Testes automatizados (3 níveis)          |
| PHPStan             | ^1.11    | Análise estática — level 5/6             |
| PHP_CodeSniffer     | ^3.10    | Padrão de código PSR-12                  |
| GitHub Actions      | —        | Pipeline CI/CD gerenciado (nuvem)        |
| Jenkins             | LTS      | Pipeline CI/CD auto-hospedado (VPS)      |
| Docker              | 29.2.0   | Contêiner do Jenkins na VPS             |
| PM2                 | 6.0.14   | Gerenciador de processo da API           |

---

## 4. Arquitetura da API

Padrão MVC simplificado com injeção de dependência manual e roteador regex customizado:

```
HTTP Request
     │
     ▼
┌─────────────────────────────┐
│       public/index.php      │  ← Entry point único
│       Router (regex)        │  ← Despacha por método + URI
└──────────────┬──────────────┘
               │
               ▼
┌─────────────────────────────┐
│         Middleware          │  ← JWT Auth, CORS, JSON headers
└──────────────┬──────────────┘
               │
               ▼
┌─────────────────────────────┐
│         Controllers         │  ← Validação de input, HTTP response
│  Auth │ User │ Expense │ Report
└──────────────┬──────────────┘
               │
               ▼
┌─────────────────────────────┐
│          Services           │  ← Regras de negócio
└──────────────┬──────────────┘
               │
               ▼
┌─────────────────────────────┐
│        Repositories         │  ← Acesso a dados (PDO)
└──────────────┬──────────────┘
               │
               ▼
          [ SQLite ]
```

### Decisões de design relevantes

- **Prepared statements** em todas as queries (prevenção de SQL Injection)
- **Bcrypt** para hashing de senhas (`password_hash` nativo)
- **JWT HS256** para autenticação stateless
- **SQLite em memória** (`:memory:`) nos testes — isolamento total, zero I/O

---

## 5. Endpoints da API

Base URL (produção): `http://76.13.112.86:8000`

### Autenticação (pública)

| Método | Rota                   | Descrição                  |
|--------|------------------------|----------------------------|
| POST   | `/api/auth/register`   | Cadastrar novo usuário     |
| POST   | `/api/auth/login`      | Autenticar e obter JWT     |

### Usuários (requer JWT)

| Método | Rota             | Descrição                      |
|--------|------------------|--------------------------------|
| GET    | `/api/users/me`  | Dados do usuário autenticado   |
| PUT    | `/api/users/me`  | Atualizar perfil               |
| DELETE | `/api/users/me`  | Excluir conta                  |

### Gastos (requer JWT)

| Método | Rota                    | Descrição                      |
|--------|-------------------------|--------------------------------|
| POST   | `/api/expenses`         | Registrar novo gasto           |
| GET    | `/api/expenses`         | Listar gastos (com filtros)    |
| GET    | `/api/expenses/{id}`    | Detalhar um gasto              |
| PUT    | `/api/expenses/{id}`    | Atualizar gasto                |
| DELETE | `/api/expenses/{id}`    | Excluir gasto                  |

### Relatórios (requer JWT)

| Método | Rota                    | Descrição                                |
|--------|-------------------------|------------------------------------------|
| GET    | `/api/reports/summary`  | Resumo de gastos por categoria e período |

---

## 6. Testes Automatizados

Três níveis de teste, todos executados em ambos os pipelines:

### Unit (`tests/Unit/`)
Testa classes em isolamento, sem banco de dados:
- `UserTest.php` — modelo de usuário
- `ExpenseTest.php` — modelo de gasto
- `AuthServiceTest.php` — lógica de autenticação

### Integration (`tests/Integration/`)
Testa repositórios com banco SQLite em memória:
- `UserRepositoryTest.php`
- `ExpenseRepositoryTest.php`

### Functional (`tests/Functional/`)
Testa a API end-to-end (request → response):
- `AuthTest.php` — fluxo de registro e login
- `ExpenseTest.php` — CRUD de gastos
- `ReportTest.php` — geração de relatórios

### Configuração (phpunit.xml)
```xml
APP_ENV=testing
DB_PATH=:memory:
JWT_SECRET=test-secret-key-for-phpunit
```

Saídas geradas:
- `junit.xml` — resultados para integração com Jenkins/Actions
- `coverage.xml` — cobertura de código (formato Clover)

---

## 7. Pipelines CI/CD

### 7.1 GitHub Actions (`.github/workflows/ci.yml`)

Trigger: push ou PR na branch `main`
Executor: `ubuntu-latest` (infraestrutura gerenciada pelo GitHub — gratuita para repositórios públicos)

**Etapas:**
1. Checkout do código (`actions/checkout@v4`)
2. Setup PHP 8.1 com extensões sqlite3, pdo_sqlite, mbstring, pcov (`shivammathur/setup-php@v2`)
3. `composer install --no-interaction --prefer-dist --no-progress`
4. PHPStan level 5: `vendor/bin/phpstan analyse src/ --level=5`
5. PHPCS PSR-12: `vendor/bin/phpcs --standard=PSR12 src/`
6. PHPUnit com cobertura: `vendor/bin/phpunit --coverage-clover=coverage.xml --coverage-text`
7. Upload de artefatos: `coverage.xml` e `junit.xml` (retidos por 30 dias)

**Variáveis de ambiente no job:**
```
APP_ENV=testing
DB_PATH=:memory:
JWT_SECRET=github-actions-ci-secret
```

---

### 7.2 Jenkins (`Jenkinsfile`)

Executor: servidor próprio na VPS Hostinger via Docker
Formato: Pipeline Declarativo (Groovy)

**Etapas:**
1. Checkout do código via SCM
2. Verificação do ambiente PHP: `php -v`, extensões, composer
3. `composer install --no-interaction --prefer-dist --no-progress`
4. PHPStan level 5
5. PHPCS PSR-12
6. PHPUnit com cobertura
7. `archiveArtifacts` — armazena `coverage.xml` e `junit.xml`

**Post-build:**
- `junit '**/junit.xml'` — publica resultados no painel Jenkins
- `cleanWs()` — limpa workspace após execução
- Mensagens de sucesso/falha no log

**Variáveis de ambiente:**
```
APP_ENV=testing
DB_PATH=:memory:
JWT_SECRET=jenkins-ci-secret
PCOV_ENABLED=1
```

---

## 8. Infraestrutura da VPS

**Provedor:** Hostinger
**IP:** `76.13.112.86`
**Hostname:** `srv1310378.hstgr.cloud`
**OS:** Ubuntu 25.10 (Questing) — kernel 6.17.0
**Armazenamento:** 192 GB disponíveis

### Serviços rodando

| Serviço       | Porta  | Gerenciador     |
|---------------|--------|-----------------|
| API PHP       | 8000   | PM2             |
| Jenkins       | 8080   | Docker Compose  |
| sorria-web    | —      | PM2             |

### docker-compose.yml (Jenkins)

```yaml
services:
  jenkins:
    image: jenkins/jenkins:lts
    container_name: jenkins_sandbox
    privileged: true
    user: root
    ports:
      - 8080:8080
      - 50000:50000
    volumes:
      - ${JENKINS_HOME_PATH}:/var/jenkins_home
      - /var/run/docker.sock:/var/run/docker.sock

  agent:
    image: jenkins/ssh-agent:jdk11
    container_name: jenkins_sandbox_agent
    privileged: true
    user: root
    expose:
      - 22
    environment:
      - JENKINS_AGENT_SSH_PUBKEY=${JENKINS_AGENT_SSH_PUBLIC_KEY}
```

Arquivo `.env` em `~/.env`:
```
JENKINS_HOME_PATH=/var/jenkins_home
JENKINS_AGENT_SSH_PUBLIC_KEY=<chave pública ~/.ssh/jenkins_agent.pub>
```

Subir: `docker compose --env-file ~/.env up -d`

### ecosystem.config.js (PM2 — API)

```js
module.exports = {
  apps: [{
    name: 'tcc-api-php',
    script: 'php',
    args: '-S 0.0.0.0:8000 -t public/',
    cwd: '/var/www/tcc-api-php',
    interpreter: 'none',
    env: {
      APP_ENV: 'production',
      DB_PATH: '/var/www/tcc-api-php/database/expenses.db',
      JWT_SECRET: '<gerado com openssl rand -hex 32>',
    },
  }],
};
```

---

## 9. Problemas Encontrados no Setup

| Problema | Causa | Solução |
|----------|-------|---------|
| PPA `ondrej/php` não suporta Ubuntu 25.10 | Ubuntu Questing muito recente | Instalado PHP 8.4 dos repositórios oficiais |
| `composer` não encontrado no PATH | `mv composer.phar` falhou silenciosamente | `cp /root/composer.phar /usr/local/bin/composer` |
| `Class "App\Config\Database" not found` | Arquivo `config/database.php` fora do mapeamento PSR-4 | Movido para `src/Config/Database.php` |
| Diretórios em minúsculo (`controllers`, `models`...) | macOS é case-insensitive, Linux não | `git mv` em dois passos para forçar rename no histórico git |
| `sed -i` quebrando no terminal web | Terminal web da Hostinger quebra linhas longas | Substituído por `python3 -c` one-liner |
| `--no-audit` não existe no Composer 2.9 | Flag depreciada | Substituído por `--no-security-blocking` |

---

## 10. Repositório

**GitHub:** `https://github.com/richard-melo/tcc-api-php`
**Branch principal:** `main`

### Commits relevantes

| Hash      | Mensagem |
|-----------|----------|
| `8634acf` | feat: initial commit — API REST de gastos pessoais |
| `ad7f05f` | feat: adiciona ecosystem.config.js para deploy com PM2 |
| `1a62dd4` | fix: move Database class to src/Config/ for autoload compatibility |
| `cf42916` | fix: rename src dirs to PascalCase for PSR-4 autoload on Linux |

---

## 11. Próximos Passos

- [ ] Configurar Job Pipeline no Jenkins (SCM → `Jenkinsfile`, branch `main`)
- [ ] Configurar webhook GitHub → Jenkins (`http://76.13.112.86:8080/github-webhook/`)
- [ ] Instalar plugin JUnit no Jenkins para publicar resultados
- [ ] Executar pipelines e coletar métricas (tempo de build, logs, cobertura)
- [ ] Comparar resultados entre GitHub Actions e Jenkins
- [ ] Redigir análise comparativa no artigo

---

## 12. Acesso aos Serviços

| Serviço | URL |
|---------|-----|
| API (produção) | `http://76.13.112.86:8000` |
| Jenkins | `http://76.13.112.86:8080` |
| GitHub Actions | `https://github.com/richard-melo/tcc-api-php/actions` |
