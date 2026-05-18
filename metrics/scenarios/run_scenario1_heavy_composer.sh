#!/usr/bin/env bash
# Cenário 1 — Heavy Composer
# Adiciona 4 dependências pesadas ao composer.json para estressar o Composer install.
# Branch: scenario/heavy-composer
#
# Uso: ./metrics/scenarios/run_scenario1_heavy_composer.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/_common.sh"

SCENARIO="heavy-composer"
BRANCH="scenario/heavy-composer"

log_h "Cenário 1 — Heavy Composer"
echo "  Branch    : $BRANCH"
echo "  Objetivo  : estressar 'composer install' com dependências pesadas"
echo "  Pacotes   : monolog/monolog, symfony/validator, guzzlehttp/guzzle, ramsey/uuid"

# ── 1. Prepara branch ──────────────────────────────────────────────────────────
prepare_branch "$BRANCH"

# ── 2. Modifica composer.json ──────────────────────────────────────────────────
log_h "Modificando composer.json"
python3 - << 'PY'
import json

path = 'composer.json'
with open(path) as f:
    data = json.load(f)

data.setdefault('require', {}).update({
    'monolog/monolog':     '^3.0',
    'symfony/validator':   '^7.0',
    'guzzlehttp/guzzle':   '^7.0',
    'ramsey/uuid':         '^4.0',
})

with open(path, 'w') as f:
    json.dump(data, f, indent=4, ensure_ascii=False)
    f.write('\n')

print("  composer.json atualizado com 4 pacotes novos")
PY

# Remove lock para forçar resolução completa de dependências
rm -f composer.lock
log_ok "composer.lock removido — Composer vai resolver do zero"

# ── 3. Commit e push ───────────────────────────────────────────────────────────
log_h "Commit e Push"
commit_push "scenario(heavy-composer): adiciona monolog, symfony/validator, guzzle, ramsey/uuid"

# ── 4. Coleta métricas ─────────────────────────────────────────────────────────
collect_all "$SCENARIO"

# ── 5. Volta para main ─────────────────────────────────────────────────────────
cd "$REPO_DIR"
git checkout main
log_ok "De volta para main. Branch '$BRANCH' mantida no GitHub."
