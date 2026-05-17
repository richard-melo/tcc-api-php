#!/usr/bin/env bash
# collect_metrics.sh — coleta métricas do GitHub Actions para o TCC
# Uso: ./collect_metrics.sh <cenário> ["mensagem do commit opcional"]
#
# Requer: 'gh' CLI autenticado  OU  variável GITHUB_TOKEN exportada
# Exemplo:
#   ./collect_metrics.sh "clean-build"
#   ./collect_metrics.sh "cold-cache" "[metrics] Run frio — sem cache"

set -euo pipefail

# ── Configuração ───────────────────────────────────────────────────────────────
REPO="richard-melo/tcc-api-php"
BRANCH="main"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
METRICS_FILE="$SCRIPT_DIR/metrics/github_actions_runs.csv"
CSV_HEADER="run_number,scenario,total_time_seconds,composer_time,phpstan_time,phpcs_time,phpunit_time,coverage_percent,tests_total,tests_passed,tests_failed,status"

SCENARIO="${1:-default}"
COMMIT_MSG="${2:-"[metrics] $SCENARIO — $(date '+%Y-%m-%dT%H:%M:%S')"}"
WORK_DIR="$(mktemp -d)"
trap 'rm -rf "$WORK_DIR"' EXIT

# ── Helper: chama GitHub API ───────────────────────────────────────────────────
_api() {
    local path="$1"; shift
    if command -v gh &>/dev/null; then
        gh api "$path" "$@"
    else
        [[ -n "${GITHUB_TOKEN:-}" ]] || {
            echo "ERRO: instale 'gh' CLI (brew install gh && gh auth login) ou exporte GITHUB_TOKEN" >&2
            exit 1
        }
        curl -sf \
            -H "Authorization: Bearer $GITHUB_TOKEN" \
            -H "Accept: application/vnd.github+json" \
            "https://api.github.com/$path" "$@"
    fi
}

# ── Helper: baixa log do job (retorna texto bruto) ─────────────────────────────
_job_log() {
    local job_id="$1"
    if command -v gh &>/dev/null; then
        gh api "repos/$REPO/actions/jobs/$job_id/logs" 2>/dev/null || echo ""
    else
        local url
        url=$(curl -sf \
            -H "Authorization: Bearer $GITHUB_TOKEN" \
            -H "Accept: application/vnd.github+json" \
            -w "%{redirect_url}" -o /dev/null \
            "https://api.github.com/repos/$REPO/actions/jobs/$job_id/logs" 2>/dev/null || echo "")
        [[ -n "$url" ]] && curl -sL "$url" 2>/dev/null || echo ""
    fi
}

# ── 1. Commit vazio + push ─────────────────────────────────────────────────────
echo "╔══════════════════════════════════════════════════════╗"
echo "║  GitHub Actions — Coleta de Métricas                ║"
echo "╚══════════════════════════════════════════════════════╝"
echo ""
echo "[1/5] Disparando pipeline via commit vazio..."
cd "$SCRIPT_DIR"
git commit --allow-empty -m "$COMMIT_MSG"
git push origin "$BRANCH"
PUSHED_SHA=$(git rev-parse HEAD)
echo "      Cenário : $SCENARIO"
echo "      SHA     : $PUSHED_SHA"

# ── 2. Aguarda o run aparecer ──────────────────────────────────────────────────
echo ""
echo "[2/5] Aguardando run aparecer no GitHub Actions..."
RUN_ID=""
for i in $(seq 1 40); do
    _api "repos/$REPO/actions/runs?head_sha=$PUSHED_SHA&per_page=1" \
        > "$WORK_DIR/runs.json" 2>/dev/null || echo "{}" > "$WORK_DIR/runs.json"

    RUN_ID=$(python3 -c "
import json
d = json.load(open('$WORK_DIR/runs.json'))
runs = d.get('workflow_runs', [])
print(runs[0]['id'] if runs else '')
" 2>/dev/null || echo "")

    if [[ -n "$RUN_ID" ]]; then
        echo "      Run ID: $RUN_ID"
        break
    fi
    printf "      Tentativa %d/40 — aguardando 5s...\r" "$i"
    sleep 5
done

if [[ -z "$RUN_ID" ]]; then
    echo "ERRO: timeout aguardando o run iniciar." >&2
    exit 1
fi

# ── 3. Aguarda conclusão ───────────────────────────────────────────────────────
echo ""
echo "[3/5] Aguardando conclusão do pipeline..."
while true; do
    _api "repos/$REPO/actions/runs/$RUN_ID" > "$WORK_DIR/run.json" 2>/dev/null

    STATUS=$(python3 -c "
import json
print(json.load(open('$WORK_DIR/run.json'))['status'])
" 2>/dev/null || echo "unknown")

    [[ "$STATUS" == "completed" ]] && break
    printf "      Status: %-20s aguardando 15s...\r" "$STATUS"
    sleep 15
done

CONCLUSION=$(python3 -c "import json; print(json.load(open('$WORK_DIR/run.json'))['conclusion'])" 2>/dev/null || echo "unknown")
RUN_NUMBER=$(python3 -c "import json; print(json.load(open('$WORK_DIR/run.json'))['run_number'])" 2>/dev/null || echo "0")
echo "      Run #$RUN_NUMBER concluído: $CONCLUSION"

# ── 4. Tempos por step ────────────────────────────────────────────────────────
echo ""
echo "[4/5] Extraindo tempos por step..."
_api "repos/$REPO/actions/runs/$RUN_ID/jobs" > "$WORK_DIR/jobs.json" 2>/dev/null

python3 - "$WORK_DIR/jobs.json" "$WORK_DIR/times.txt" "$WORK_DIR/job_id.txt" << 'PY'
import json, sys
from datetime import datetime

def secs(start, end):
    fmt = lambda t: datetime.fromisoformat(t.replace('Z', '+00:00'))
    return max(0, int((fmt(end) - fmt(start)).total_seconds()))

data = json.load(open(sys.argv[1]))
jobs = data.get('jobs', [])

if not jobs:
    open(sys.argv[2], 'w').write("0 0 0 0 0\n")
    open(sys.argv[3], 'w').write("\n")
    sys.exit(0)

job = jobs[0]
open(sys.argv[3], 'w').write(str(job['id']) + "\n")

total = secs(job['started_at'], job['completed_at'])
steps = {}
for s in job.get('steps', []):
    if s.get('started_at') and s.get('completed_at'):
        steps[s['name']] = secs(s['started_at'], s['completed_at'])

composer = steps.get('Instalar dependências (Composer)', 0)
phpstan  = steps.get('Análise estática — PHPStan level 5', 0)
phpcs    = steps.get('Padrões de código — PHPCS PSR-12', 0)
phpunit  = steps.get('Testes automatizados — PHPUnit', 0)

open(sys.argv[2], 'w').write(f"{total} {composer} {phpstan} {phpcs} {phpunit}\n")
print(f"      total={total}s  composer={composer}s  phpstan={phpstan}s  phpcs={phpcs}s  phpunit={phpunit}s")
PY

read -r TOTAL COMPOSER PHPSTAN PHPCS PHPUNIT < "$WORK_DIR/times.txt"
JOB_ID=$(cat "$WORK_DIR/job_id.txt" | tr -d '\n')

# ── 5. Cobertura e contagem de testes ─────────────────────────────────────────
echo ""
echo "[5/5] Extraindo cobertura e contagem de testes..."
COVERAGE="N/A"
TESTS_TOTAL="N/A"
TESTS_PASSED="N/A"
TESTS_FAILED="N/A"

# Estratégia A: parse do log do job (busca COVERAGE_PERCENT: X)
if [[ -n "$JOB_ID" ]]; then
    echo "      Baixando log do job $JOB_ID..."
    _job_log "$JOB_ID" > "$WORK_DIR/job.log" 2>/dev/null || true
    if [[ -s "$WORK_DIR/job.log" ]]; then
        COV_LINE=$(grep "COVERAGE_PERCENT:" "$WORK_DIR/job.log" | tail -1 || echo "")
        if [[ -n "$COV_LINE" ]]; then
            COVERAGE=$(echo "$COV_LINE" | grep -oP '[\d.]+' | tail -1)
            echo "      Cobertura (log): $COVERAGE%"
        fi
    fi
fi

# Estratégia B: download do artefato coverage.xml
if [[ "$COVERAGE" == "N/A" ]]; then
    _api "repos/$REPO/actions/runs/$RUN_ID/artifacts" > "$WORK_DIR/artifacts.json" 2>/dev/null

    ARTIFACT_ID=$(python3 -c "
import json
arts = json.load(open('$WORK_DIR/artifacts.json')).get('artifacts', [])
match = next((a for a in arts if a['name'] == 'coverage-report'), None)
print(match['id'] if match else '')
" 2>/dev/null || echo "")

    if [[ -n "$ARTIFACT_ID" ]]; then
        mkdir -p "$WORK_DIR/artifact"
        if command -v gh &>/dev/null; then
            gh run download "$RUN_ID" \
                --repo "$REPO" \
                --name "coverage-report" \
                --dir "$WORK_DIR/artifact/" 2>/dev/null || true
        else
            ARTIFACT_URL=$(curl -sf \
                -H "Authorization: Bearer $GITHUB_TOKEN" \
                -H "Accept: application/vnd.github+json" \
                -w "%{redirect_url}" -o /dev/null \
                "https://api.github.com/repos/$REPO/actions/artifacts/$ARTIFACT_ID/zip" \
                2>/dev/null || echo "")
            [[ -n "$ARTIFACT_URL" ]] && curl -sL "$ARTIFACT_URL" -o "$WORK_DIR/artifact.zip" \
                && unzip -q "$WORK_DIR/artifact.zip" -d "$WORK_DIR/artifact/" 2>/dev/null || true
        fi

        if [[ -f "$WORK_DIR/artifact/coverage.xml" ]]; then
            COVERAGE=$(python3 -c "
import xml.etree.ElementTree as ET
try:
    tree = ET.parse('$WORK_DIR/artifact/coverage.xml')
    m = tree.find('.//metrics')
    s = int(m.get('statements', 0))
    c = int(m.get('coveredstatements', 0))
    print(round(c / s * 100, 2) if s > 0 else 'N/A')
except:
    print('N/A')
" 2>/dev/null || echo "N/A")
            echo "      Cobertura (artifact): $COVERAGE%"
        fi

        if [[ -f "$WORK_DIR/artifact/junit.xml" ]]; then
            read -r TESTS_TOTAL TESTS_PASSED TESTS_FAILED < <(python3 -c "
import xml.etree.ElementTree as ET
try:
    root = ET.parse('$WORK_DIR/artifact/junit.xml').getroot()
    suite = root.find('.//testsuite') or root
    total  = int(suite.get('tests', 0))
    fails  = int(suite.get('failures', 0)) + int(suite.get('errors', 0))
    passed = total - fails
    print(total, passed, fails)
except:
    print('N/A N/A N/A')
" 2>/dev/null || echo "N/A N/A N/A")
            echo "      Testes: total=$TESTS_TOTAL  passed=$TESTS_PASSED  failed=$TESTS_FAILED"
        fi
    fi
fi

# ── Salvar CSV ────────────────────────────────────────────────────────────────
mkdir -p "$(dirname "$METRICS_FILE")"
[[ ! -f "$METRICS_FILE" ]] && echo "$CSV_HEADER" > "$METRICS_FILE"

CSV_LINE="$RUN_NUMBER,$SCENARIO,$TOTAL,$COMPOSER,$PHPSTAN,$PHPCS,$PHPUNIT,$COVERAGE,$TESTS_TOTAL,$TESTS_PASSED,$TESTS_FAILED,$CONCLUSION"
echo "$CSV_LINE" >> "$METRICS_FILE"

echo ""
echo "══════════════════════════════════════════════════════"
echo "  Métricas salvas: $METRICS_FILE"
echo "  $CSV_LINE"
echo "══════════════════════════════════════════════════════"
