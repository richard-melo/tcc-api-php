# Convenção de Commits — Letras Temáticas

Este projeto usa uma convenção própria de mensagens de commit que combina
**Conventional Commits** com **letras de músicas tematicamente relacionadas** à mudança.

## Formato

```
lyrics: '<trecho da letra>' — <tipo>: <descrição curta>
```

**Exemplos:**

```
lyrics: 'i've been going through changes' — fix: correct PSR-12 violations flagged by PHPCS
lyrics: 'building something out of nothing' — feat: add JWT authentication middleware
lyrics: 'taking control' — ci: add GitHub Actions workflow for PHP tests
```

---

## Tipos de commit

| Tipo       | Quando usar                                      |
|------------|--------------------------------------------------|
| `feat`     | Nova funcionalidade visível ao usuário           |
| `fix`      | Correção de bug ou violação de padrão            |
| `refactor` | Reestruturação sem mudança de comportamento      |
| `ci`       | Mudanças em pipelines, GitHub Actions, Jenkinsfile |
| `infra`    | Configuração de servidor, PM2, Nginx, env        |
| `docs`     | Documentação, README, comentários                |
| `test`     | Adição ou correção de testes                     |
| `chore`    | Tarefas menores (deps, configs menores)          |

---

## Convenção de letras — relação temática

A letra **não é aleatória**. Ela deve refletir o *sentimento* ou *metáfora* da mudança.

| Tipo de mudança | Temática sugerida para a letra |
|-----------------|--------------------------------|
| `fix`           | correção, limpeza, consertar algo quebrado, encarar problemas |
| `feat`          | criação, início, construção, crescimento, possibilidade |
| `refactor`      | transformação, mudança, evolução, recomeço |
| `ci` / `infra`  | controle, automação, processo, estrutura, poder |
| `docs`          | comunicação, explicação, clareza, contar histórias |
| `test`          | verificação, certeza, confiança, provar algo |
| `chore`         | rotina, manutenção, persistência |

### Exemplos por tipo

**`fix`** — consertar algo quebrado:
- *"i've been going through changes"* — Billie Eilish
- *"fix you"* — Coldplay
- *"cleaning out my closet"* — Eminem

**`feat`** — criar algo novo:
- *"building something out of nothing"*
- *"started from the bottom now we're here"* — Drake
- *"this is the beginning of something"*

**`refactor`** — transformar sem quebrar:
- *"same soul, different coat"*
- *"same love"* — Macklemore
- *"metamorphosis"*

**`ci` / `infra`** — automatizar e controlar:
- *"I'm taking over my body"* — Glass Animals
- *"taking control"*
- *"power"* — Kanye West

**`docs`** — explicar e comunicar:
- *"let me tell you something"*
- *"the story of us"* — Taylor Swift

**`test`** — verificar e garantir:
- *"I need proof"*
- *"trust but verify"*
- *"show me"*

---

## Como escolher a letra

1. Entenda o **tipo** do commit (`fix`, `feat`, etc.)
2. Pense na **metáfora central** da mudança — o que ela significa além do técnico?
3. Busque uma letra que ressoe com essa metáfora
4. Prefira trechos curtos (até ~10 palavras), impactantes, que façam sentido fora do contexto musical

Não precisa ser da sua música favorita — precisa fazer sentido com a mudança.

---

## Script auxiliar

O script `scripts/update-changelog.sh` lê o último commit e atualiza o `CHANGELOG.md` automaticamente.

```bash
# Após fazer o commit, rode:
./scripts/update-changelog.sh 0.4.0   # com versão
./scripts/update-changelog.sh         # como Unreleased
```

---

## Referências

- [Conventional Commits](https://www.conventionalcommits.org/pt-br/)
- [Keep a Changelog](https://keepachangelog.com/pt-BR/)
- [Semantic Versioning](https://semver.org/)
