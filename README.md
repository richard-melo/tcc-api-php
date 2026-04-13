# tcc-api-php

> API REST de gerenciamento de gastos pessoais desenvolvida em **PHP 8.1+ puro** como artefato do TCC-II — comparação entre **GitHub Actions** e **Jenkins** como plataformas de CI/CD.

[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![GitHub Actions](https://img.shields.io/badge/GitHub_Actions-CI-2088FF?logo=github-actions&logoColor=white)](https://github.com/features/actions)
[![PHPUnit](https://img.shields.io/badge/PHPUnit-10.x-366488?logo=php&logoColor=white)](https://phpunit.de/)
[![PHPStan](https://img.shields.io/badge/PHPStan-level_6-4B5EAA)](https://phpstan.org/)
[![PSR-12](https://img.shields.io/badge/code_style-PSR--12-brightgreen)](https://www.php-fig.org/psr/psr-12/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

---

## Sobre o Projeto

**tcc-api-php** é uma API RESTful de gerenciamento de gastos pessoais construída sem nenhum framework externo — apenas PHP 8.1+ nativo, SQLite e uma biblioteca de JWT.

O projeto é o artefato prático do **Trabalho de Conclusão de Curso II** (Engenharia de Software — UNIVILLE), cujo objetivo é comparar empiricamente dois pipelines de integração e entrega contínua equivalentes — um no **GitHub Actions** e outro no **Jenkins** — avaliando critérios como tempo de execução, facilidade de configuração, rastreabilidade e custo operacional.

### Por que PHP puro?
A decisão de não usar um framework (Laravel, Symfony) foi deliberada: ela garante que o pipeline de CI/CD seja o foco real da avaliação, sem que a complexidade do framework interfira nos resultados. Isso também evidencia o domínio dos fundamentos da linguagem.

---

## Destaques Técnicos

| Aspecto | Decisão |
|---|---|
| **Framework** | Nenhum — roteador regex customizado, DI manual |
| **Banco de dados** | SQLite via PDO — portável, sem instalação |
| **Autenticação** | JWT HS256 (`firebase/php-jwt`) + bcrypt para senhas |
| **Segurança** | Prepared statements em todas as queries (SQL injection prevention) |
| **Testes** | 3 níveis: Unit, Integração e Funcional |
| **Qualidade** | PHPStan level 6 (análise estática) + PHPCodeSniffer PSR-12 |
| **CI/CD** | Dois pipelines completos e equivalentes para comparação direta |

---

## Arquitetura

A API segue um padrão MVC simplificado com separação clara de responsabilidades:

```
HTTP Request
     │
     ▼
┌─────────────────────────────────────┐
│           public/index.php          │  ← Entry point único
│          Router (regex)             │  ← Despacha por método + rota
└─────────────┬───────────────────────┘
              │
              ▼
┌─────────────────────────────────────┐
│           Middleware                │  ← JWT Auth, CORS, JSON
└─────────────┬───────────────────────┘
              │
              ▼
┌─────────────────────────────────────┐
│           Controllers               │  ← Validação de input, HTTP response
│  Auth │ User │ Expense │ Report     │
└─────────────┬───────────────────────┘
              │
              ▼
┌─────────────────────────────────────┐
│            Services                 │  ← Regras de negócio
│  AuthService │ ExpenseService │ ... │
└─────────────┬───────────────────────┘
              │
              ▼
┌─────────────────────────────────────┐
│          Repositories               │  ← Acesso a dados (PDO)
│  UserRepo │ ExpenseRepo │ ...       │
└─────────────┬───────────────────────┘
              │
              ▼
         [ SQLite ]
```

---

## Endpoints da API

### Autenticação

| Método | Rota | Descrição | Auth |
|--------|------|-----------|------|
| `POST` | `/api/auth/register` | Cadastrar novo usuário | Não |
| `POST` | `/api/auth/login` | Autenticar e obter JWT | Não |

### Usuários

| Método | Rota | Descrição | Auth |
|--------|------|-----------|------|
| `GET` | `/api/users/me` | Dados do usuário autenticado | Sim |
| `PUT` | `/api/users/me` | Atualizar perfil | Sim |
| `DELETE` | `/api/users/me` | Excluir conta | Sim |

### Gastos

| Método | Rota | Descrição | Auth |
|--------|------|-----------|------|
| `POST` | `/api/expenses` | Registrar novo gasto | Sim |
| `GET` | `/api/expenses` | Listar gastos (com filtros) | Sim |
| `GET` | `/api/expenses/{id}` | Detalhar um gasto | Sim |
| `PUT` | `/api/expenses/{id}` | Atualizar gasto | Sim |
| `DELETE` | `/api/expenses/{id}` | Excluir gasto | Sim |

### Relatórios

| Método | Rota | Descrição | Auth |
|--------|------|-----------|------|
| `GET` | `/api/reports/summary` | Resumo de gastos por categoria e período | Sim |

---

## Stack

| Tecnologia | Versão | Função |
|---|---|---|
| PHP | 8.1+ | Linguagem principal |
| SQLite | 3.x | Banco de dados embutido |
| firebase/php-jwt | ^6.10 | Geração e validação de JWT |
| PHPUnit | ^10.5 | Testes automatizados |
| PHPStan | ^1.11 | Análise estática (level 6) |
| PHP_CodeSniffer | ^3.10 | Padrão de código PSR-12 |

---

## Como Executar Localmente

**Pré-requisitos:** PHP 8.1+, Composer, extensão `pdo_sqlite`

```bash
# Clonar o repositório
git clone https://github.com/seu-usuario/tcc-api-php.git
cd tcc-api-php/api

# Instalar dependências
composer install

# Executar todos os testes
APP_ENV=testing DB_PATH=:memory: JWT_SECRET=secret composer test

# Apenas testes unitários
composer test:unit

# Apenas testes de integração
composer test:integration

# Apenas testes funcionais
composer test:functional

# Análise estática
composer stan

# Verificar padrão de código
composer cs

# Subir servidor de desenvolvimento
php -S localhost:8080 -t public/
```

---

## Pipelines CI/CD

Este projeto implementa **dois pipelines equivalentes** para fins de comparação acadêmica.

### GitHub Actions (`.github/workflows/ci.yml`)

Pipeline declarativo baseado em YAML, executado na infraestrutura gerenciada do GitHub:

1. **Setup** — checkout + configuração do PHP 8.1 com extensões necessárias
2. **Cache** — restauração do cache do Composer para builds mais rápidos
3. **Dependências** — `composer install --no-interaction`
4. **Testes** — execução do PHPUnit com geração de relatório de cobertura
5. **Análise Estática** — PHPStan level 6
6. **Padrão de Código** — PHP_CodeSniffer PSR-12

### Jenkins (`Jenkinsfile`)

Pipeline imperativo baseado em Groovy (Jenkinsfile declarativo), executado em servidor próprio:

1. **Checkout** — clone do repositório via SCM
2. **Install** — `composer install`
3. **Test** — PHPUnit com saída JUnit para integração nativa com Jenkins
4. **Static Analysis** — PHPStan
5. **Code Style** — PHPCS
6. **Relatório** — publicação automática dos resultados no painel Jenkins

> **Objetivo da comparação:** ambos os pipelines executam as mesmas etapas de validação. A pesquisa mede e compara tempo de build, facilidade de configuração, rastreabilidade dos resultados, curva de aprendizado e custo operacional entre as duas plataformas.

---

## Estrutura de Diretórios

```
api/
├── .github/
│   └── workflows/
│       └── ci.yml              # Pipeline GitHub Actions
├── config/
│   └── database.php            # Configuração do banco SQLite
├── public/
│   └── index.php               # Entry point da aplicação
├── src/
│   ├── controllers/            # Camada HTTP (request/response)
│   │   ├── AuthController.php
│   │   ├── ExpenseController.php
│   │   ├── ReportController.php
│   │   └── UserController.php
│   ├── Http/                   # Router e Response helpers
│   ├── middleware/             # JWT Auth, CORS
│   ├── models/                 # Entidades do domínio
│   ├── repositories/           # Acesso a dados (PDO/SQLite)
│   └── services/               # Regras de negócio
├── tests/
│   ├── Unit/                   # Testes de unidade isolados
│   ├── Integration/            # Testes com banco real (in-memory)
│   ├── Functional/             # Testes end-to-end da API
│   └── Support/                # Helpers e fixtures de teste
├── Jenkinsfile                 # Pipeline Jenkins
├── composer.json
├── phpcs.xml                   # Regras PSR-12
├── phpstan.neon                # Configuração análise estática
└── phpunit.xml                 # Suítes de teste
```

---

## Autor

**Richard Luiz Melo Neto**
Bacharelando em Engenharia de Software — UNIVILLE (Joinville, SC)

Orientadora: **Profª Rafaela Bosse Schroeder**

---

> Trabalho de Conclusão de Curso II — Engenharia de Software — UNIVILLE — 2026
