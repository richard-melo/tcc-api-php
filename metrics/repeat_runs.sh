#!/usr/bin/env bash
# metrics/repeat_runs.sh — repete N runs de um cenário para dados estatísticos
#
# Uso:
#   ./metrics/repeat_runs.sh clean-build-expanded 5
#   ./metrics/repeat_runs.sh full-heavy 5
#   ./metrics/repeat_runs.sh full-heavy 5 3    # retoma a partir do run 3
#
# Variável opcional:
#   GITHUB_TOKEN=ghp_xxx ./metrics/repeat_runs.sh ...   (evita rate limit de 60 req/h)

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/scenarios/_common.sh"

SCENARIO="${1:-clean-build-expanded}"
REPEAT="${2:-5}"
START_FROM="${3:-1}"
CLEAN_CSV="$METRICS_DIR/clean_data.csv"
CLEAN_HEADER="scenario,tool,run_number,total_time,composer_time,phpstan_time,phpcs_time,phpunit_time,coverage_percent,tests_total,tests_passed,tests_failed,status,notes"

# ── Branch para cada cenário ───────────────────────────────────────────────────
case "$SCENARIO" in
    clean-build-expanded) BRANCH="main" ;;
    full-heavy)           BRANCH="scenario/full-heavy" ;;
    *)                    BRANCH="scenario/$SCENARIO" ;;
esac

log_h "Repetições — $SCENARIO  ($REPEAT runs, início: $START_FROM)"
echo "  Branch : $BRANCH"
echo "  CSV    : $CLEAN_CSV"
[[ -n "${GITHUB_TOKEN:-}" ]] && echo "  Auth   : GITHUB_TOKEN definido (5000 req/h)" \
                              || echo "  Auth   : sem token (60 req/h — use GITHUB_TOKEN=ghp_xxx)"
echo ""

# ── Garante header no CSV ──────────────────────────────────────────────────────
[[ ! -f "$CLEAN_CSV" ]] && echo "$CLEAN_HEADER" > "$CLEAN_CSV"

# ── Garante branch correta ─────────────────────────────────────────────────────
cd "$REPO_DIR"
git checkout "$BRANCH"
git pull origin "$BRANCH" --ff-only 2>/dev/null || git pull origin "$BRANCH"

# ── Configura Jenkins para a branch correta ────────────────────────────────────
jenkins_save_config
jenkins_set_branch "$BRANCH"
log_ok "Jenkins configurado → $BRANCH"

# ══════════════════════════════════════════════════════════════════════════════
# Nota: loop usa 'run_idx' (não 'i') para evitar conflito com a variável
# interna de gha_wait_for_run(), que também usa 'i' sem declarar 'local i'.
for run_idx in $(seq "$START_FROM" "$REPEAT"); do
    log_h "Run $run_idx / $REPEAT — $SCENARIO"

    # Diretório temporário exigido por gha_collect/jenkins_collect para arquivos intermediários
    WORK_DIR=$(mktemp -d)

    # ── Commit vazio → dispara GHA ─────────────────────────────────────────────
    cd "$REPO_DIR"
    git commit --allow-empty -m "[metrics] $SCENARIO repeticao $run_idx/$REPEAT"
    git push origin "$BRANCH"
    PUSHED_SHA=$(git rev-parse HEAD)
    log_ok "Push — SHA: $PUSHED_SHA"

    # ── GitHub Actions ─────────────────────────────────────────────────────────
    log_h "GitHub Actions — Run $run_idx"
    gha_wait_for_run "$PUSHED_SHA"
    gha_wait_complete "$GHA_RUN_ID"
    gha_collect "$GHA_RUN_ID"

    # ── Jenkins ────────────────────────────────────────────────────────────────
    log_h "Jenkins — Run $run_idx"
    jenkins_trigger
    jenkins_wait "$JENKINS_BUILD_NUMBER"
    jenkins_collect "$JENKINS_BUILD_NUMBER"

    rm -rf "$WORK_DIR"

    # ── Grava linhas diretamente no CSV (dados preservados mesmo se o script falhar)
    GHA_NOTE="repeticao_${run_idx}_de_${REPEAT};cobertura_derivada_jenkins"
    J_NOTE="repeticao_${run_idx}_de_${REPEAT}"

    GHA_LINE="$SCENARIO,github_actions,$GHA_RUN_NUMBER,$G_TOTAL,$G_COMPOSER,$G_PHPSTAN,$G_PHPCS,$G_PHPUNIT,$J_COVERAGE,$J_TESTS_TOTAL,$J_TESTS_PASSED,$J_TESTS_FAILED,$G_STATUS,$GHA_NOTE"
    J_LINE="$SCENARIO,jenkins,$JENKINS_BUILD_NUMBER,$J_TOTAL,$J_COMPOSER,$J_PHPSTAN,$J_PHPCS,$J_PHPUNIT,$J_COVERAGE,$J_TESTS_TOTAL,$J_TESTS_PASSED,$J_TESTS_FAILED,$J_STATUS,$J_NOTE"

    echo "$GHA_LINE" >> "$CLEAN_CSV"
    echo "$J_LINE"   >> "$CLEAN_CSV"

    log_ok "GHA  #$GHA_RUN_NUMBER  → ${G_TOTAL}s  ($G_STATUS)"
    log_ok "Jenk #$JENKINS_BUILD_NUMBER → ${J_TOTAL}s  ($J_STATUS)"

    # ── Pausa entre runs: 150s para respeitar rate limit da API GitHub (60 req/h)
    if [[ "$run_idx" -lt "$REPEAT" ]]; then
        log "Aguardando 150s antes do próximo run (rate limit GitHub API)..."
        sleep 150
    fi
done
# ══════════════════════════════════════════════════════════════════════════════

# ── Restaura Jenkins → main ────────────────────────────────────────────────────
jenkins_restore_config

# ── Commita CSV na main ────────────────────────────────────────────────────────
cd "$REPO_DIR"
if [[ "$BRANCH" != "main" ]]; then
    git checkout main
    git pull origin main --ff-only 2>/dev/null || true
fi

git add "$CLEAN_CSV"
git commit -m "metrics: ${REPEAT} repeticoes do cenario $SCENARIO para analise estatistica"
git push origin main
log_ok "Dados commitados na main"

# ── Resumo estatístico (filtra só as linhas de repetição do cenário atual) ─────
log_h "Resumo estatístico — $SCENARIO (repetições)"
python3 - "$CLEAN_CSV" "$SCENARIO" << 'PY'
import sys, csv, statistics

csv_path = sys.argv[1]
scenario = sys.argv[2]

rows = []
with open(csv_path, newline='') as f:
    reader = csv.DictReader(f)
    for r in reader:
        if r['scenario'] == scenario and 'repeticao' in r.get('notes', ''):
            rows.append(r)

if not rows:
    print("  Nenhuma linha de repetição encontrada.")
    sys.exit(0)

print(f"\n  {'Tool':<16} {'Run':>5} {'Total':>6} {'Comp':>5} {'PHPStan':>8} {'PHPCS':>5} {'PHPUnit':>8} {'Cov%':>6}  Status")
print("  " + "─" * 72)
for r in rows:
    print(f"  {r['tool']:<16} {r['run_number']:>5} {r['total_time']:>5}s {r['composer_time']:>4}s "
          f"{r['phpstan_time']:>7}s {r['phpcs_time']:>4}s {r['phpunit_time']:>7}s "
          f"{r['coverage_percent']:>6}  {r['status']}")

print()
for tool in ["github_actions", "jenkins"]:
    totals = [int(r['total_time']) for r in rows if r['tool'] == tool
              and r['total_time'].lstrip("-").isdigit()]
    if not totals:
        continue
    avg = statistics.mean(totals)
    std = statistics.stdev(totals) if len(totals) > 1 else 0.0
    print(f"  {tool:<16}  n={len(totals)}  avg={avg:.1f}s  std={std:.2f}s  "
          f"min={min(totals)}s  max={max(totals)}s  range={max(totals)-min(totals)}s")
PY
