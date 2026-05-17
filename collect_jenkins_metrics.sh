#!/usr/bin/env bash
# collect_jenkins_metrics.sh — coleta métricas do Jenkins para o TCC
# Uso: ./collect_jenkins_metrics.sh <cenário> [--trigger]
#
# Variáveis de ambiente (opcionais se Jenkins não tem autenticação):
#   JENKINS_USER  — usuário Jenkins (ex: admin)
#   JENKINS_TOKEN — API token do Jenkins (gerado em: <jenkins>/user/<user>/configure)
#
# Exemplos:
#   ./collect_jenkins_metrics.sh "clean-build"          # usa o último build
#   ./collect_jenkins_metrics.sh "clean-build" --trigger # dispara novo build e coleta

set -euo pipefail

# ── Configuração ───────────────────────────────────────────────────────────────
JENKINS_URL="http://76.13.112.86:8080"
JOB_NAME="tcc-api-php"
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
    local auth_args=()
    if [[ -n "${JENKINS_USER:-}" && -n "${JENKINS_TOKEN:-}" ]]; then
        auth_args=(-u "$JENKINS_USER:$JENKINS_TOKEN")
    fi
    curl -sf "${auth_args[@]}" "$JENKINS_URL$path" "$@"
}

_jenkins_post() {
    local path="$1"; shift
    local auth_args=()
    if [[ -n "${JENKINS_USER:-}" && -n "${JENKINS_TOKEN:-}" ]]; then
        auth_args=(-u "$JENKINS_USER:$JENKINS_TOKEN")
    fi

    # Obtém crumb (proteção CSRF do Jenkins)
    local crumb_data
    crumb_data=$(_jenkins "/crumbIssuer/api/json" 2>/dev/null || echo "{}")
    local crumb_field crumb_value
    crumb_field=$(python3 -c "import json; d=json.loads('$crumb_data'); print(d.get('crumbRequestField','Jenkins-Crumb'))" 2>/dev/null || echo "Jenkins-Crumb")
    crumb_value=$(python3 -c "import json; d=json.loads('$crumb_data'); print(d.get('crumb',''))" 2>/dev/null || echo "")

    curl -sf "${auth_args[@]}" \
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
    echo "      Verifique se o serviço está rodando e a VPS acessível." >&2
    exit 1
fi
echo "      Conectado!"

# ── Opcionalmente dispara um novo build ───────────────────────────────────────
if [[ "$TRIGGER_BUILD" == "--trigger" ]]; then
    echo ""
    echo "[2/4] Disparando novo build..."

    LAST_BUILD_BEFORE=$(python3 -c "
import json
d = json.load(open('$WORK_DIR/job_info.json'))
lb = d.get('lastBuild')
print(lb['number'] if lb else 0)
" 2>/dev/null || echo "0")

    _jenkins_post "/job/$JOB_NAME/build" > /dev/null 2>&1 || true
    echo "      Build disparado (último antes: #$LAST_BUILD_BEFORE)"
    echo "      Aguardando novo build aparecer..."

    for i in $(seq 1 30); do
        sleep 5
        _jenkins "/job/$JOB_NAME/api/json" > "$WORK_DIR/job_info.json" 2>/dev/null || continue
        NEW_LAST=$(python3 -c "
import json
d = json.load(open('$WORK_DIR/job_info.json'))
lb = d.get('lastBuild')
print(lb['number'] if lb else 0)
" 2>/dev/null || echo "0")
        if [[ "$NEW_LAST" -gt "$LAST_BUILD_BEFORE" ]]; then
            echo "      Novo build #$NEW_LAST iniciado!"
            break
        fi
        printf "      Tentativa %d/30...\r" "$i"
    done
else
    echo ""
    echo "[2/4] Usando último build existente (sem --trigger)"
fi

# ── Aguarda conclusão do build ─────────────────────────────────────────────────
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

echo "      Build #$BUILD_NUMBER — $BUILD_RESULT — ${TOTAL}s"

# ── Extrai tempos por stage via Workflow API ───────────────────────────────────
echo ""
echo "[4/4] Extraindo tempos por stage..."
_jenkins "/job/$JOB_NAME/lastBuild/wfapi/describe" > "$WORK_DIR/wfapi.json" 2>/dev/null || echo "{}" > "$WORK_DIR/wfapi.json"

python3 - "$WORK_DIR/wfapi.json" "$WORK_DIR/stage_times.txt" << 'PY'
import json, sys

data = json.load(open(sys.argv[1]))
stages = data.get('stages', [])

def ms_to_s(ms):
    return max(0, round(ms / 1000))

stage_map = {s['name']: ms_to_s(s.get('durationMillis', 0)) for s in stages}

composer = stage_map.get('Instalar dependências (Composer)', 0)
phpstan  = stage_map.get('Análise estática — PHPStan level 5', 0)
phpcs    = stage_map.get('Padrões de código — PHPCS PSR-12', 0)
phpunit  = stage_map.get('Testes automatizados — PHPUnit', 0)

open(sys.argv[2], 'w').write(f"{composer} {phpstan} {phpcs} {phpunit}\n")

print(f"      composer={composer}s  phpstan={phpstan}s  phpcs={phpcs}s  phpunit={phpunit}s")
if not stages:
    print("      (Workflow API sem dados — verifique o plugin Pipeline no Jenkins)")
PY

read -r COMPOSER PHPSTAN PHPCS PHPUNIT < "$WORK_DIR/stage_times.txt"

# Testes via JUnit plugin
TESTS_TOTAL="N/A"; TESTS_PASSED="N/A"; TESTS_FAILED="N/A"
if _jenkins "/job/$JOB_NAME/lastBuild/testReport/api/json" > "$WORK_DIR/test_report.json" 2>/dev/null; then
    read -r TESTS_TOTAL TESTS_PASSED TESTS_FAILED < <(python3 -c "
import json
try:
    d = json.load(open('$WORK_DIR/test_report.json'))
    total  = d.get('totalCount', 0)
    failed = d.get('failCount', 0)
    passed = total - failed
    print(total, passed, failed)
except:
    print('N/A N/A N/A')
" 2>/dev/null || echo "N/A N/A N/A")
    echo "      Testes: total=$TESTS_TOTAL  passed=$TESTS_PASSED  failed=$TESTS_FAILED"
fi

# Jenkins não coleta cobertura (Jenkinsfile usa --no-coverage)
COVERAGE="N/A"

# Mapeia resultado Jenkins → formato padrão
STATUS=$(echo "$BUILD_RESULT" | tr '[:upper:]' '[:lower:]' | sed 's/success/success/;s/failure/failure/;s/unstable/failure/;s/aborted/failure/')

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
