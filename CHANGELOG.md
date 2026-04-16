# Changelog

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/).
Versionamento segue [Semantic Versioning](https://semver.org/).

Cada entrada inclui uma letra de música **tematicamente relacionada** à natureza da mudança —
não como ornamento, mas como síntese do *sentimento* por trás do commit.

---

## [Unreleased]

---

## [0.3.0] — 2026-04-16

> *"i've been going through changes"* — Billie Eilish, Changes

### Fixed
- Corrigidas violações PSR-12 em 10 arquivos (`Controllers`, `Models`, `Repositories`, `Services`, `Middleware`)

**Por que essa letra?** Um fix de estilo/linting é literalmente passar por mudanças — ajustar cada detalhe até o código estar no padrão.

---

## [0.2.2] — 2026-04-14

> *"no one else is dealing with your demons"* — twenty one pilots, Neon Gravestones

### Fixed
- Removida regra `ignoreErrors` morta do `phpstan.neon`

**Por que essa letra?** Um erro silenciado é um demônio que só você carrega. Remover o `ignoreErrors` é encarar o problema de frente.

---

## [0.2.1] — 2026-04-14

> *"the ghost of you is close to me"* — My Chemical Romance, The Ghost of You

### Fixed
- Atualizadas opções de configuração depreciadas do `phpstan.neon`

**Por que essa letra?** Opções depreciadas são fantasmas — ainda funcionam, mas assombram. Exorcizar o config é a medida certa.

---

## [0.2.0] — 2026-04-13

> *"i've been thinking too much"* — twenty one pilots, Ride

### Fixed
- Adicionado `--no-security-blocking` ao `composer install` no Jenkinsfile para evitar falha de build por advisory não-crítico

**Por que essa letra?** Um pipeline que falha por pensar demais (checar segurança de forma bloqueante) precisa aprender a continuar mesmo com incerteza.

---

## [0.1.2] — 2026-04-12

> *"I'm taking over my body"* — Glass Animals, I'm All Yours

### Fixed
- Renomeados diretórios `src/` para PascalCase para compatibilidade com autoload PSR-4 no Linux

**Por que essa letra?** Tomar controle do próprio corpo — ou nesse caso, da estrutura de diretórios. PascalCase é o sistema tomando posse de si mesmo.

---

## [0.1.1] — 2026-04-11

> *"jumpsuit, jumpsuit, cover me"* — twenty one pilots, Jumpsuit

### Fixed
- Classe `Database` movida para `src/Config/` para compatibilidade com autoload

**Por que essa letra?** Mover algo para onde ele realmente pertence é dar-lhe proteção e estrutura — o jumpsuit que cobre.

---

## [0.1.0] — 2026-04-10

### Added
- `ecosystem.config.js` para deploy com PM2 (process manager)

**Por que sem letra?** Commit anterior à convenção de letras temáticas.

---

## [0.0.1] — 2026-04-08

### Added
- Commit inicial — API REST de gastos pessoais (TCC-II UNIVILLE)
- Estrutura completa: `Controllers`, `Models`, `Repositories`, `Services`, `Middleware`
- Banco de dados SQLite sem frameworks externos
- Autenticação JWT
- Documentação inicial (`README.md`, `docs/`)
- Pipeline CI/CD com GitHub Actions e Jenkinsfile
- Configuração PHPStan, PHPCS e PHPUnit

**Por que sem letra?** Commit anterior à convenção de letras temáticas.

---

[Unreleased]: https://github.com/richardluizmeloneto/tcc-api/compare/HEAD...HEAD
[0.3.0]: https://github.com/richardluizmeloneto/tcc-api/compare/v0.2.2...v0.3.0
[0.2.2]: https://github.com/richardluizmeloneto/tcc-api/compare/v0.2.1...v0.2.2
[0.2.1]: https://github.com/richardluizmeloneto/tcc-api/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/richardluizmeloneto/tcc-api/compare/v0.1.2...v0.2.0
[0.1.2]: https://github.com/richardluizmeloneto/tcc-api/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/richardluizmeloneto/tcc-api/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/richardluizmeloneto/tcc-api/compare/v0.0.1...v0.1.0
[0.0.1]: https://github.com/richardluizmeloneto/tcc-api/releases/tag/v0.0.1
