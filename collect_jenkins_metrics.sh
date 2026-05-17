#!/usr/bin/env bash
# collect_jenkins_metrics.sh — coleta métricas do Jenkins para o TCC
# Uso: ./collect_jenkins_metrics.sh <cenário> [--trigger]
#
# Variáveis de ambiente (opcionais se Jenkins não tem autenticação):
#   JENKINS_USER  — usuário Jenkins (padrão: richard)
#   JENKINS_TOKEN — senha ou API token do Jenkins
#
# Exemplos:
#   ./collect_jenkins_metrics.sh "clean-build"          # usa o último build
#   ./collect_jenkins_metrics.sh "clean-build" --trigger # dispara novo build

set -euo pipefail

# ── Configuração ───────────────────────────────────────────────────────────────
JENKINS_URL="${JENKINS_URL:-http://76.13.112.86:8080}"
JOB_NAME="tcc-api-php"
JENKINS_USER="${JENKINS_USER:-richard}"
JENKINS_TOKEN="${JENKINS_TOKEN:-richard77}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
METRICS_FILE="$SCRIPT_DIR/metrics/jenkins_runs.csv"
CSV_HEADER="run_number,scenario,total_time_seconds,composer_time,phpstan_time,phpcs_time,phpunit_time,coverage_percent,tests_total,tests_passed,tests_failed,status"

SCENARIO="${1:-default}"
TRIGGER_BUILD="${2:-}"
WORK_DIR="$(mktemp -d)"
trap 'rm -rf "$WORK_DIR"' EXIT

# ── Helper: chama Jenkins API ──────────────────────────────────────────────────
_jenkins() {
    local path="$1"; shift
    curl -sf -u "$JENKINS_USER:$JENKINS_TOKEN" "$JENKINS_URL$path" "$@"
}

_jenkins_post() {
    local path="$1"; shift
    # Obtém crumb (proteção CSRF)
    local crumb_json crumb_field crumb_value
    crumb_json=$(curl -sf -u "$JENKINS_USER:$JENKINS_TOKEN" "$JENKINS_URL/crumbIssuer/api/json" 2>/dev/null || echo "{}")
    crumb_field=$(python3 -c "import json; d=json.loads('$crumb_json'); print(d.get('crumbRequestField','Jenkins-Crumb'))" 2>/dev/null || echo "Jenkins-Crumb")
    crumb_value=$(python3 -c "import json; d=json.loads('$crumb_json'); print(d.get('crumb',''))" 2>/dev/null || echo "")

    curl -sf -u "$JENKINS_USER:$JENKINS_TOKEN" \
        -X POST \
        -H "$crumb_field: $crumb_value" \
        "$JENKINS_URL$path" "$@"
}

echo "╔══════════════════════════════════════════════════════╗"
echo "║  Jenkins — Coleta de Métricas                       ║"
echo "╚══════════════════════════════════════════════════════╝"
echo ""
echo "    Cenário : $SCENARIO"
echo "    Jenkins : $JENKINS_URL/job/$JOB_NAME"

# ── Verifica conectividade ─────────────────────────────────────────────────────
echo ""
echo "[1/4] Verificando conexão com Jenkins..."
if ! _jenkins "/job/$JOB_NAME/api/json" > "$WORK_DIR/job_info.json" 2>/dev/null; then
    echo "ERRO: Não foi possível conectar ao Jenkins em $JENKINS_URL" >&2
    exit 1
fi
echo "      Conectado!"

# ── Opcionalmente dispara um novo build ───────────────────────────────────────
if [[ "$TRIGGER_BUILD" == "--trigger" ]]; then
    echo ""
    echo "[2/4] Disparando novo build..."
    LAST_BEFORE=$(python3 -c "
import json
d = json.load(open('$WORK_DIR/job_info.json'))
lb = d.get('lastBuild')
print(lb['number'] if lb else 0)
" 2>/dev/null || echo "0")

    _jenkins_post "/job/$JOB_NAME/build" > /dev/null 2>&1 || true
    echo "      Build disparado (último antes: #$LAST_BEFORE). Aguardando novo build..."

    for i in $(seq 1 30); do
        sleep 5
        _jenkins "/job/$JOB_NAME/api/json" > "$WORK_DIR/job_info.json" 2>/dev/null || continue
        NEW_LAST=$(python3 -c "
import json
d = json.load(open('$WORK_DIR/job_info.json'))
lb = d.get('lastBuild')
print(lb['number'] if lb else 0)
" 2>/dev/null || echo "0")
        if [[ "$NEW_LAST" -gt "$LAST_BEFORE" ]]; then
            echo "      Novo build #$NEW_LAST iniciado!"
            break
        fi
        printf "      Tentativa %d/30...\r" "$i"
    done
else
    echo ""
    echo "[2/4] Usando último build existente (sem --trigger)"
fi

# ── Aguarda conclusão ──────────────────────────────────────────────────────────
echo ""
echo "[3/4] Aguardando conclusão do build..."
while true; do
    _jenkins "/job/$JOB_NAME/lastBuild/api/json" > "$WORK_DIR/build.json" 2>/dev/null
    BUILDING=$(python3 -c "
import json
print(str(json.load(open('$WORK_DIR/build.json')).get('building', False)).lower())
" 2>/dev/null || echo "true")

    [[ "$BUILDING" == "false" ]] && break
    printf "      Em andamento... aguardando 10s\r"
    sleep 10
done

BUILD_NUMBER=$(python3 -c "import json; print(json.load(open('$WORK_DIR/build.json'))['number'])" 2>/dev/null || echo "0")
BUILD_RESULT=$(python3 -c "import json; print(json.load(open('$WORK_DIR/build.json')).get('result','UNKNOWN'))" 2>/dev/null || echo "UNKNOWN")
BUILD_DURATION_MS=$(python3 -c "import json; print(json.load(open('$WORK_DIR/build.json')).get('duration', 0))" 2>/dev/null || echo "0")
TOTAL=$(python3 -c "print(round($BUILD_DURATION_MS / 1000))" 2>/dev/null || echo "0")
TESTS_TOTAL=$(python3 -c "
import json
d = json.load(open('$WORK_DIR/build.json'))
actions = d.get('actions', [])
r = next((a for a in actions if a.get('_class','') == 'hudson.tasks.junit.TestResultAction'), None)
print(r.get('totalCount', 'N/A') if r else 'N/A')
" 2>/dev/null || echo "N/A")
TESTS_FAILED=$(python3 -c "
import json
d = json.load(open('$WORK_DIR/build.json'))
actions = d.get('actions', [])
r = next((a for a in actions if a.get('_class','') == 'hudson.tasks.junit.TestResultAction'), None)
print(r.get('failCount', 'N/A') if r else 'N/A')
" 2>/dev/null || echo "N/A")
TESTS_PASSED=$(python3 -c "
t='$TESTS_TOTAL'; f='$TESTS_FAILED'
print(int(t)-int(f) if t.isdigit() and f.isdigit() else 'N/A')
" 2>/dev/null || echo "N/A")

echo "      Build #$BUILD_NUMBER — $BUILD_RESULT — ${TOTAL}s"
echo "      Testes: total=$TESTS_TOTAL  passed=$TESTS_PASSED  failed=$TESTS_FAILED"

# ── Extrai tempos por stage e cobertura do console log ────────────────────────
echo ""
echo "[4/4] Extraindo tempos por stage e cobertura do log..."
_jenkins "/job/$JOB_NAME/$BUILD_NUMBER/consoleText" > "$WORK_DIR/console.log" 2>/dev/null || echo "" > "$WORK_DIR/console.log"

parse_stage_time() {
    local key="$1"
    local ms
    ms=$(grep "STAGE_TIME_${key}:" "$WORK_DIR/console.log" 2>/dev/null \
        | grep -oP '\d+' | tail -1 || echo "")
    if [[ -n "$ms" && "$ms" != "0" ]]; then
        python3 -c "print(round($ms / 1000))"
    else
        echo "N/A"
    fi
}

COMPOSER=$(parse_stage_time "composer")
PHPSTAN=$(parse_stage_time "phpstan")
PHPCS=$(parse_stage_time "phpcs")
PHPUNIT=$(parse_stage_time "phpunit")

COVERAGE=$(grep "COVERAGE_PERCENT:" "$WORK_DIR/console.log" 2>/dev/null \
    | grep -oP '[\d.]+' | tail -1 || echo "N/A")
[[ -z "$COVERAGE" ]] && COVERAGE="N/A"

echo "      composer=${COMPOSER}s  phpstan=${PHPSTAN}s  phpcs=${PHPCS}s  phpunit=${PHPUNIT}s"
echo "      Cobertura: $COVERAGE%"

# Normaliza resultado Jenkins → success/failure
STATUS=$(echo "$BUILD_RESULT" | tr '[:upper:]' '[:lower:]' \
    | sed 's/unstable/failure/;s/aborted/failure/')

# ── Salvar CSV ────────────────────────────────────────────────────────────────
mkdir -p "$(dirname "$METRICS_FILE")"
[[ ! -f "$METRICS_FILE" ]] && echo "$CSV_HEADER" > "$METRICS_FILE"

CSV_LINE="$BUILD_NUMBER,$SCENARIO,$TOTAL,$COMPOSER,$PHPSTAN,$PHPCS,$PHPUNIT,$COVERAGE,$TESTS_TOTAL,$TESTS_PASSED,$TESTS_FAILED,$STATUS"
echo "$CSV_LINE" >> "$METRICS_FILE"

echo ""
echo "══════════════════════════════════════════════════════"
echo "  Métricas salvas: $METRICS_FILE"
echo "  $CSV_LINE"
echo "══════════════════════════════════════════════════════"
