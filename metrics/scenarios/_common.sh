#!/usr/bin/env bash
# metrics/scenarios/_common.sh — funções compartilhadas para coleta de métricas por cenário
# Uso: source "$(dirname "${BASH_SOURCE[0]}")/_common.sh"

set -euo pipefail

# ── Configuração ───────────────────────────────────────────────────────────────
JENKINS_URL="${JENKINS_URL:-http://76.13.112.86:8080}"
JENKINS_USER="${JENKINS_USER:-richard}"
JENKINS_TOKEN="${JENKINS_TOKEN:-richard77}"
GITHUB_TOKEN="${GITHUB_TOKEN:-}"
JOB_NAME="tcc-api-php"
REPO="richard-melo/tcc-api-php"

COMMON_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "$COMMON_DIR/../.." && pwd)"
METRICS_DIR="$REPO_DIR/metrics"
GHA_CSV="$METRICS_DIR/github_actions_runs.csv"
JENKINS_CSV="$METRICS_DIR/jenkins_runs.csv"
CSV_HEADER="run_number,scenario,total_time_seconds,composer_time,phpstan_time,phpcs_time,phpunit_time,coverage_percent,tests_total,tests_passed,tests_failed,status"

WORK_DIR=""
JENKINS_ORIGINAL_CONFIG=""
PUSHED_SHA=""
GHA_NEW_LINE=""
JENKINS_NEW_LINE=""

# ── Logging ───────────────────────────────────────────────────────────────────
log_h()   { echo; echo "══════════════════════════════════════════════════════"; echo "  $*"; echo "══════════════════════════════════════════════════════"; }
log()     { echo "  [$(date +%H:%M:%S)] $*"; }
log_ok()  { echo "  ✔ $*"; }
log_err() { echo "  ✘ $*" >&2; }

# ── GitHub API: wrapper com autenticação opcional ─────────────────────────────
# Exportar GITHUB_TOKEN evita o rate limit de 60 req/h (sem token → 5000 req/h)
_gha_curl() {
    if [[ -n "${GITHUB_TOKEN:-}" ]]; then
        curl -sf -H "Authorization: Bearer $GITHUB_TOKEN" "$@"
    else
        curl -sf "$@"
    fi
}

# ── Jenkins: chamadas HTTP ─────────────────────────────────────────────────────
_j_get() {
    curl -sf -u "$JENKINS_USER:$JENKINS_TOKEN" "$JENKINS_URL$1"
}

_j_post_action() {
    local path="$1"; shift
    local cookie_jar; cookie_jar="/tmp/j_cookies_$$.txt"
    rm -f "$cookie_jar"

    local crumb_json crumb_field crumb_value
    crumb_json=$(curl -sf -u "$JENKINS_USER:$JENKINS_TOKEN" -c "$cookie_jar" \
        "$JENKINS_URL/crumbIssuer/api/json" 2>/dev/null || echo '{}')
    crumb_field=$(python3 -c "import json,sys; d=json.load(sys.stdin); print(d.get('crumbRequestField','Jenkins-Crumb'))" <<< "$crumb_json" 2>/dev/null || echo 'Jenkins-Crumb')
    crumb_value=$(python3 -c "import json,sys; d=json.load(sys.stdin); print(d.get('crumb',''))" <<< "$crumb_json" 2>/dev/null || echo '')

    curl -sf -u "$JENKINS_USER:$JENKINS_TOKEN" \
        -b "$cookie_jar" \
        -X POST \
        -H "$crumb_field: $crumb_value" \
        "$JENKINS_URL$path" "$@"
    local ret=$?
    rm -f "$cookie_jar"
    return $ret
}

_j_post_xml() {
    local path="$1"
    local xml_content="$2"
    local cookie_jar; cookie_jar="/tmp/j_xml_$$.txt"
    rm -f "$cookie_jar"

    local crumb_json crumb_field crumb_value
    crumb_json=$(curl -sf -u "$JENKINS_USER:$JENKINS_TOKEN" -c "$cookie_jar" \
        "$JENKINS_URL/crumbIssuer/api/json" 2>/dev/null || echo '{}')
    crumb_field=$(python3 -c "import json,sys; d=json.load(sys.stdin); print(d.get('crumbRequestField','Jenkins-Crumb'))" <<< "$crumb_json" 2>/dev/null || echo 'Jenkins-Crumb')
    crumb_value=$(python3 -c "import json,sys; d=json.load(sys.stdin); print(d.get('crumb',''))" <<< "$crumb_json" 2>/dev/null || echo '')

    echo "$xml_content" | curl -sf -u "$JENKINS_USER:$JENKINS_TOKEN" \
        -b "$cookie_jar" \
        -X POST \
        -H "$crumb_field: $crumb_value" \
        -H "Content-Type: application/xml" \
        --data-binary @- \
        "$JENKINS_URL$path" > /dev/null 2>&1
    local ret=$?
    rm -f "$cookie_jar"
    return $ret
}

# ── Jenkins: config branch switching ──────────────────────────────────────────
jenkins_save_config() {
    JENKINS_ORIGINAL_CONFIG=$(_j_get "/job/$JOB_NAME/config.xml")
    log_ok "Config Jenkins salva"
}

jenkins_restore_config() {
    [[ -z "$JENKINS_ORIGINAL_CONFIG" ]] && return 0
    _j_post_xml "/job/$JOB_NAME/config.xml" "$JENKINS_ORIGINAL_CONFIG" || true
    log_ok "Jenkins restaurado → branch main"
}

jenkins_set_branch() {
    local branch="$1"
    local new_config
    new_config=$(BRANCH_SPEC="*/$branch" python3 -c "
import sys, os, re
config = sys.stdin.read()
spec = os.environ['BRANCH_SPEC']
new = re.sub(r'<name>\*/[^<]+</name>', f'<name>{spec}</name>', config, count=1)
print(new, end='')
" <<< "$JENKINS_ORIGINAL_CONFIG")

    _j_post_xml "/job/$JOB_NAME/config.xml" "$new_config"
    log_ok "Jenkins configurado → branch: $branch"
}

# ── Jenkins: trigger e espera ──────────────────────────────────────────────────
jenkins_get_last_build_number() {
    _j_get "/job/$JOB_NAME/api/json" \
        | python3 -c "import json,sys; d=json.load(sys.stdin); lb=d.get('lastBuild'); print(lb['number'] if lb else 0)" 2>/dev/null || echo "0"
}

jenkins_trigger() {
    local before
    before=$(jenkins_get_last_build_number)
    _j_post_action "/job/$JOB_NAME/build" > /dev/null 2>&1 || true
    log "Build disparado (último antes: #$before). Aguardando #$((before+1))..."

    for _ in $(seq 1 30); do
        sleep 5
        local cur
        cur=$(jenkins_get_last_build_number)
        if [[ "$cur" -gt "$before" ]]; then
            JENKINS_BUILD_NUMBER="$cur"
            log_ok "Build #$JENKINS_BUILD_NUMBER iniciado"
            return 0
        fi
    done
    log_err "Timeout aguardando novo build"
    return 1
}

JENKINS_BUILD_NUMBER=""

jenkins_wait() {
    local build="${1:-$JENKINS_BUILD_NUMBER}"
    log "Aguardando build #$build..."
    while true; do
        local building
        building=$(_j_get "/job/$JOB_NAME/$build/api/json" \
            | python3 -c "import json,sys; print(str(json.load(sys.stdin).get('building',True)).lower())" 2>/dev/null || echo "true")
        [[ "$building" == "false" ]] && break
        printf "      em andamento... aguarda 10s\r"
        sleep 10
    done
    echo ""
}

# ── Jenkins: coleta métricas do console log ────────────────────────────────────
# Exporta: J_TOTAL J_COMPOSER J_PHPSTAN J_PHPCS J_PHPUNIT J_COVERAGE
#          J_TESTS_TOTAL J_TESTS_PASSED J_TESTS_FAILED J_STATUS
jenkins_collect() {
    local build="${1:-$JENKINS_BUILD_NUMBER}"
    local wdir="$WORK_DIR"

    _j_get "/job/$JOB_NAME/$build/api/json" > "$wdir/j_build.json"
    _j_get "/job/$JOB_NAME/$build/consoleText" > "$wdir/j_console.log" 2>/dev/null || true

    local dur_ms result
    dur_ms=$(python3 -c "import json; print(json.load(open('$wdir/j_build.json')).get('duration',0))" 2>/dev/null || echo 0)
    result=$(python3 -c "import json; print(json.load(open('$wdir/j_build.json')).get('result','UNKNOWN'))" 2>/dev/null || echo UNKNOWN)

    J_TOTAL=$(python3 -c "print(round($dur_ms/1000))" 2>/dev/null || echo 0)
    J_STATUS=$(echo "$result" | tr '[:upper:]' '[:lower:]' | sed 's/unstable/failure/;s/aborted/failure/')

    _j_parse_stage() {
        local key="$1"
        local ms
        ms=$(grep "STAGE_TIME_${key}:" "$wdir/j_console.log" 2>/dev/null \
            | grep -oE '[0-9]+' | tail -1 || echo "")
        if [[ -n "$ms" && "$ms" != "0" ]]; then
            python3 -c "print(round($ms/1000))"
        else
            echo "N/A"
        fi
    }

    J_COMPOSER=$(  _j_parse_stage "composer")
    J_PHPSTAN=$(   _j_parse_stage "phpstan")
    J_PHPCS=$(     _j_parse_stage "phpcs")
    J_PHPUNIT=$(   _j_parse_stage "phpunit")

    J_COVERAGE=$(grep "COVERAGE_PERCENT:" "$wdir/j_console.log" 2>/dev/null \
        | grep -oE '[0-9]+\.[0-9]+|[0-9]+' | tail -1 || echo "N/A")
    [[ -z "$J_COVERAGE" ]] && J_COVERAGE="N/A"

    J_TESTS_TOTAL=$(python3 -c "
import json
d = json.load(open('$wdir/j_build.json'))
r = next((a for a in d.get('actions',[]) if a.get('_class','')=='hudson.tasks.junit.TestResultAction'),None)
print(r.get('totalCount','N/A') if r else 'N/A')
" 2>/dev/null || echo "N/A")
    J_TESTS_FAILED=$(python3 -c "
import json
d = json.load(open('$wdir/j_build.json'))
r = next((a for a in d.get('actions',[]) if a.get('_class','')=='hudson.tasks.junit.TestResultAction'),None)
print(r.get('failCount','N/A') if r else 'N/A')
" 2>/dev/null || echo "N/A")
    J_TESTS_PASSED=$(python3 -c "
t='$J_TESTS_TOTAL'; f='$J_TESTS_FAILED'
print(int(t)-int(f) if t.isdigit() and f.isdigit() else 'N/A')
" 2>/dev/null || echo "N/A")

    log_ok "Jenkins #$build — ${J_STATUS} — ${J_TOTAL}s | composer=${J_COMPOSER}s phpstan=${J_PHPSTAN}s phpcs=${J_PHPCS}s phpunit=${J_PHPUNIT}s | cov=${J_COVERAGE}% tests=${J_TESTS_TOTAL}"
}

# ── GitHub Actions: aguarda e coleta ──────────────────────────────────────────
# Exporta: G_RUN_ID G_RUN_NUMBER G_TOTAL G_COMPOSER G_PHPSTAN G_PHPCS G_PHPUNIT G_STATUS
GHA_RUN_ID=""
GHA_RUN_NUMBER=""

gha_wait_for_run() {
    local sha="${1:-$PUSHED_SHA}"
    log "Aguardando run no GitHub Actions para SHA $sha..."
    for i in $(seq 1 40); do
        local runs
        runs=$(_gha_curl -H "Accept: application/vnd.github+json" \
            "https://api.github.com/repos/$REPO/actions/runs?head_sha=$sha&per_page=1" 2>/dev/null || echo '{}')
        GHA_RUN_ID=$(python3 -c "
import json,sys
d = json.loads(sys.argv[1])
runs = d.get('workflow_runs',[])
print(runs[0]['id'] if runs else '')
" "$runs" 2>/dev/null || echo "")
        if [[ -n "$GHA_RUN_ID" ]]; then
            log_ok "GHA Run ID: $GHA_RUN_ID"
            return 0
        fi
        printf "      tentativa %d/40 — aguarda 5s...\r" "$i"
        sleep 5
    done
    log_err "Timeout: run GHA não encontrado"
    return 1
}

gha_wait_complete() {
    local run_id="${1:-$GHA_RUN_ID}"
    log "Aguardando conclusão do run #$run_id..."
    while true; do
        local data status conclusion
        data=$(_gha_curl -H "Accept: application/vnd.github+json" \
            "https://api.github.com/repos/$REPO/actions/runs/$run_id" 2>/dev/null || echo '{}')
        status=$(python3 -c "import json,sys; print(json.load(sys.stdin)['status'])" <<< "$data" 2>/dev/null || echo "unknown")
        conclusion=$(python3 -c "import json,sys; print(json.load(sys.stdin).get('conclusion','null'))" <<< "$data" 2>/dev/null || echo "null")
        GHA_RUN_NUMBER=$(python3 -c "import json,sys; print(json.load(sys.stdin).get('run_number','?'))" <<< "$data" 2>/dev/null || echo "?")
        [[ "$status" == "completed" ]] && { log_ok "GHA Run #$GHA_RUN_NUMBER — $conclusion"; break; }
        printf "      status: %-20s aguarda 15s...\r" "$status"
        sleep 15
    done
    echo ""
}

gha_collect() {
    local run_id="${1:-$GHA_RUN_ID}"
    local wdir="$WORK_DIR"

    # Run metadata (total time, status)
    local run_data
    run_data=$(_gha_curl -H "Accept: application/vnd.github+json" \
        "https://api.github.com/repos/$REPO/actions/runs/$run_id" 2>/dev/null || echo '{}')
    G_STATUS=$(python3 -c "import json,sys; print(json.load(sys.stdin).get('conclusion','unknown'))" <<< "$run_data" 2>/dev/null || echo "unknown")

    # Step times from jobs API (public, no auth needed)
    local jobs_data
    jobs_data=$(_gha_curl -H "Accept: application/vnd.github+json" \
        "https://api.github.com/repos/$REPO/actions/runs/$run_id/jobs" 2>/dev/null || echo '{}')

    JOBS_JSON="$jobs_data" python3 - "$wdir/g_times.txt" << 'PY'
import json, sys, os
from datetime import datetime

def secs(s, e):
    fmt = lambda t: datetime.fromisoformat(t.replace('Z', '+00:00'))
    return max(0, int((fmt(e) - fmt(s)).total_seconds()))

data = json.loads(os.environ['JOBS_JSON'])
jobs = data.get('jobs', [])

if not jobs:
    open(sys.argv[1], 'w').write("0 0 0 0 0\n")
    sys.exit(0)

job = jobs[0]
total = secs(job['started_at'], job['completed_at'])
steps = {}
for step in job.get('steps', []):
    if step.get('started_at') and step.get('completed_at'):
        steps[step['name']] = secs(step['started_at'], step['completed_at'])

composer = steps.get('Instalar dependências (Composer)', 0)
phpstan  = steps.get('Análise estática — PHPStan level 5', 0)
phpcs    = steps.get('Padrões de código — PHPCS PSR-12', 0)
phpunit  = steps.get('Testes automatizados — PHPUnit', 0)
open(sys.argv[1], 'w').write(f"{total} {composer} {phpstan} {phpcs} {phpunit}\n")
PY

    read -r G_TOTAL G_COMPOSER G_PHPSTAN G_PHPCS G_PHPUNIT < "$wdir/g_times.txt"
    log_ok "GHA Run #$GHA_RUN_NUMBER — ${G_STATUS} — ${G_TOTAL}s | composer=${G_COMPOSER}s phpstan=${G_PHPSTAN}s phpcs=${G_PHPCS}s phpunit=${G_PHPUNIT}s"
}

# ── Git helpers ────────────────────────────────────────────────────────────────
prepare_branch() {
    local branch="$1"
    cd "$REPO_DIR"
    git checkout main
    git pull origin main --ff-only 2>/dev/null || git pull origin main
    git branch -D "$branch" 2>/dev/null || true
    git checkout -b "$branch"
    log_ok "Branch criada: $branch"
}

commit_push() {
    local message="$1"
    cd "$REPO_DIR"
    git add -A
    git commit -m "$message"
    git push origin "$(git branch --show-current)" --force-with-lease 2>/dev/null \
        || git push origin "$(git branch --show-current)"
    PUSHED_SHA=$(git rev-parse HEAD)
    log_ok "Push: $PUSHED_SHA"
}

# ── CSV ────────────────────────────────────────────────────────────────────────
save_gha_csv() {
    local scenario="$1"
    GHA_NEW_LINE="$GHA_RUN_NUMBER,$scenario,$G_TOTAL,$G_COMPOSER,$G_PHPSTAN,$G_PHPCS,$G_PHPUNIT,N/A,N/A,N/A,N/A,$G_STATUS"
    log_ok "GHA linha pronta: $GHA_NEW_LINE"
}

save_jenkins_csv() {
    local scenario="$1"
    JENKINS_NEW_LINE="$JENKINS_BUILD_NUMBER,$scenario,$J_TOTAL,$J_COMPOSER,$J_PHPSTAN,$J_PHPCS,$J_PHPUNIT,$J_COVERAGE,$J_TESTS_TOTAL,$J_TESTS_PASSED,$J_TESTS_FAILED,$J_STATUS"
    log_ok "Jenkins linha pronta: $JENKINS_NEW_LINE"
}

commit_metrics_to_main() {
    local scenario="$1"
    local gha_line="$2"
    local jenkins_line="$3"

    cd "$REPO_DIR"
    git checkout -f main
    git pull origin main --ff-only 2>/dev/null || true

    mkdir -p "$METRICS_DIR"
    [[ ! -f "$GHA_CSV" ]] && echo "$CSV_HEADER" > "$GHA_CSV"
    echo "$gha_line" >> "$GHA_CSV"

    [[ ! -f "$JENKINS_CSV" ]] && echo "$CSV_HEADER" > "$JENKINS_CSV"
    echo "$jenkins_line" >> "$JENKINS_CSV"

    git add "$GHA_CSV" "$JENKINS_CSV"
    git commit -m "metrics: registra cenário $scenario (GHA #$GHA_RUN_NUMBER / Jenkins #$JENKINS_BUILD_NUMBER)"
    git push origin main
    log_ok "Métricas commitadas na main. Branch '$scenario' mantida no GitHub."
}

# ── Orquestração principal ────────────────────────────────────────────────────
collect_all() {
    local scenario="$1"
    WORK_DIR=$(mktemp -d)
    trap 'rm -rf "$WORK_DIR"' EXIT

    log_h "GitHub Actions — aguardando e coletando"
    gha_wait_for_run "$PUSHED_SHA"
    gha_wait_complete "$GHA_RUN_ID"
    gha_collect "$GHA_RUN_ID"
    save_gha_csv "$scenario"

    log_h "Jenkins — configurando branch e coletando"
    jenkins_save_config
    trap 'jenkins_restore_config; rm -rf "$WORK_DIR"' EXIT

    jenkins_set_branch "$(git -C "$REPO_DIR" branch --show-current)"
    jenkins_trigger
    jenkins_wait "$JENKINS_BUILD_NUMBER"
    jenkins_collect "$JENKINS_BUILD_NUMBER"
    save_jenkins_csv "$scenario"

    jenkins_restore_config
    trap 'rm -rf "$WORK_DIR"' EXIT

    log_h "Resumo — $scenario"
    printf "  %-28s %14s %14s\n" "Métrica" "GitHub Actions" "Jenkins"
    printf "  %-28s %14s %14s\n" "─────────────────────────────" "──────────────" "──────────────"
    printf "  %-28s %14s %14s\n" "Total (s)"     "$G_TOTAL"    "$J_TOTAL"
    printf "  %-28s %14s %14s\n" "Composer (s)"  "$G_COMPOSER" "$J_COMPOSER"
    printf "  %-28s %14s %14s\n" "PHPStan (s)"   "$G_PHPSTAN"  "$J_PHPSTAN"
    printf "  %-28s %14s %14s\n" "PHPCS (s)"     "$G_PHPCS"    "$J_PHPCS"
    printf "  %-28s %14s %14s\n" "PHPUnit (s)"   "$G_PHPUNIT"  "$J_PHPUNIT"
    printf "  %-28s %14s %14s\n" "Cobertura (%)" "N/A"         "$J_COVERAGE"
    printf "  %-28s %14s %14s\n" "Status"        "$G_STATUS"   "$J_STATUS"
    echo ""
}
