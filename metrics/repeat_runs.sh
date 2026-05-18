#!/usr/bin/env bash
# metrics/repeat_runs.sh — repete N runs de um cenário para dados estatísticos
#
# Uso:
#   ./metrics/repeat_runs.sh clean-build-expanded 5
#   ./metrics/repeat_runs.sh full-heavy 5

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/scenarios/_common.sh"

SCENARIO="${1:-clean-build-expanded}"
REPEAT="${2:-5}"
CLEAN_CSV="$METRICS_DIR/clean_data.csv"
CLEAN_HEADER="scenario,tool,run_number,total_time,composer_time,phpstan_time,phpcs_time,phpunit_time,coverage_percent,tests_total,tests_passed,tests_failed,status,notes"

# ── Branch para cada cenário ───────────────────────────────────────────────────
case "$SCENARIO" in
    clean-build-expanded) BRANCH="main" ;;
    full-heavy)           BRANCH="scenario/full-heavy" ;;
    *)                    BRANCH="scenario/$SCENARIO" ;;
esac

# ── Buffer temporário para novas linhas ────────────────────────────────────────
TMP_ROWS=$(mktemp)
trap 'rm -f "$TMP_ROWS"' EXIT

log_h "Repetições — $SCENARIO  ($REPEAT runs)"
echo "  Branch : $BRANCH"
echo "  CSV    : $CLEAN_CSV"
echo ""

# ── Garante branch correta ─────────────────────────────────────────────────────
cd "$REPO_DIR"
git checkout "$BRANCH"
git pull origin "$BRANCH" --ff-only 2>/dev/null || git pull origin "$BRANCH"

# ── Configura Jenkins para a branch correta ────────────────────────────────────
jenkins_save_config
jenkins_set_branch "$BRANCH"
log_ok "Jenkins configurado → $BRANCH"

# ══════════════════════════════════════════════════════════════════════════════
for i in $(seq 1 "$REPEAT"); do
    log_h "Run $i / $REPEAT — $SCENARIO"

    # Trabalho temporário para collect
    WORK_DIR=$(mktemp -d)

    # ── Commit vazio → dispara GHA ─────────────────────────────────────────────
    cd "$REPO_DIR"
    git commit --allow-empty -m "[metrics] $SCENARIO repeticao $i/$REPEAT"
    git push origin "$BRANCH"
    PUSHED_SHA=$(git rev-parse HEAD)
    log_ok "Push — SHA: $PUSHED_SHA"

    # ── GitHub Actions ─────────────────────────────────────────────────────────
    log_h "GitHub Actions — Run $i"
    gha_wait_for_run "$PUSHED_SHA"
    gha_wait_complete "$GHA_RUN_ID"
    gha_collect "$GHA_RUN_ID"

    # ── Jenkins ────────────────────────────────────────────────────────────────
    log_h "Jenkins — Run $i"
    jenkins_trigger
    jenkins_wait "$JENKINS_BUILD_NUMBER"
    jenkins_collect "$JENKINS_BUILD_NUMBER"

    # ── Grava linhas no buffer ─────────────────────────────────────────────────
    GHA_NOTE="repeticao_${i}_de_${REPEAT};cobertura_derivada_jenkins"
    J_NOTE="repeticao_${i}_de_${REPEAT}"

    GHA_LINE="$SCENARIO,github_actions,$GHA_RUN_NUMBER,$G_TOTAL,$G_COMPOSER,$G_PHPSTAN,$G_PHPCS,$G_PHPUNIT,$J_COVERAGE,$J_TESTS_TOTAL,$J_TESTS_PASSED,$J_TESTS_FAILED,$G_STATUS,$GHA_NOTE"
    J_LINE="$SCENARIO,jenkins,$JENKINS_BUILD_NUMBER,$J_TOTAL,$J_COMPOSER,$J_PHPSTAN,$J_PHPCS,$J_PHPUNIT,$J_COVERAGE,$J_TESTS_TOTAL,$J_TESTS_PASSED,$J_TESTS_FAILED,$J_STATUS,$J_NOTE"

    echo "$GHA_LINE" >> "$TMP_ROWS"
    echo "$J_LINE"   >> "$TMP_ROWS"

    log_ok "GHA  #$GHA_RUN_NUMBER  → ${G_TOTAL}s  ($G_STATUS)"
    log_ok "Jenk #$JENKINS_BUILD_NUMBER → ${J_TOTAL}s  ($J_STATUS)"

    rm -rf "$WORK_DIR"

    # ── Pausa entre runs ───────────────────────────────────────────────────────
    if [[ "$i" -lt "$REPEAT" ]]; then
        log "Aguardando 30s antes do próximo run..."
        sleep 30
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

[[ ! -f "$CLEAN_CSV" ]] && echo "$CLEAN_HEADER" > "$CLEAN_CSV"
cat "$TMP_ROWS" >> "$CLEAN_CSV"

git add "$CLEAN_CSV"
git commit -m "metrics: ${REPEAT} repeticoes do cenario $SCENARIO para analise estatistica"
git push origin main
log_ok "Dados commitados na main"

# ── Resumo estatístico ─────────────────────────────────────────────────────────
log_h "Resumo estatístico — $SCENARIO"
python3 - "$TMP_ROWS" << 'PY'
import sys, csv, statistics

rows = []
with open(sys.argv[1]) as f:
    for r in csv.reader(f):
        rows.append(r)

print(f"\n  {'Tool':<16} {'Run':>5} {'Total':>6} {'Comp':>5} {'PHPStan':>8} {'PHPCS':>5} {'PHPUnit':>8} {'Cov%':>6}  Status")
print("  " + "─" * 72)
for r in rows:
    sc, tool, run, total, comp, phs, pcs, phu, cov, tt, tp, tf, st, *_ = r
    print(f"  {tool:<16} {run:>5} {total:>5}s {comp:>4}s {phs:>7}s {pcs:>4}s {phu:>7}s {cov:>6}  {st}")

print()
for tool in ["github_actions", "jenkins"]:
    totals = [int(r[3]) for r in rows if r[1] == tool and r[3].lstrip("-").isdigit()]
    if not totals:
        continue
    avg = statistics.mean(totals)
    std = statistics.stdev(totals) if len(totals) > 1 else 0.0
    print(f"  {tool:<16}  avg={avg:.1f}s  std={std:.2f}s  min={min(totals)}s  max={max(totals)}s  range={max(totals)-min(totals)}s")
PY
