# Métricas de CI/CD — TCC

Dados coletados para comparação entre **GitHub Actions** e **Jenkins** no contexto do TCC.

## Estrutura dos arquivos

```
metrics/
├── github_actions_runs.csv   # Execuções do GitHub Actions
├── jenkins_runs.csv          # Execuções do Jenkins
└── README.md                 # Este arquivo
```

## Formato do CSV

Ambos os arquivos compartilham o mesmo schema:

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `run_number` | int | Número sequencial do run/build |
| `scenario` | string | Nome do cenário de teste (ex: `clean-build`, `cold-cache`) |
| `total_time_seconds` | int | Tempo total do pipeline em segundos |
| `composer_time` | int | Tempo do step de instalação de dependências (s) |
| `phpstan_time` | int | Tempo do step de análise estática PHPStan (s) |
| `phpcs_time` | int | Tempo do step de padrões de código PHPCS (s) |
| `phpunit_time` | int | Tempo do step de testes PHPUnit (s) |
| `coverage_percent` | float ou N/A | Cobertura de linha em % (GitHub Actions apenas) |
| `tests_total` | int ou N/A | Total de casos de teste executados |
| `tests_passed` | int ou N/A | Casos de teste que passaram |
| `tests_failed` | int ou N/A | Casos de teste que falharam |
| `status` | string | Resultado final: `success` ou `failure` |

> **Nota:** O Jenkinsfile executa `phpunit --no-coverage`, então `coverage_percent` será sempre `N/A`
> no `jenkins_runs.csv`. Isso é intencional para comparar o overhead de cobertura entre as plataformas.

## Scripts de coleta

### GitHub Actions — `collect_metrics.sh`

```bash
# Pré-requisito: gh CLI autenticado
brew install gh && gh auth login

# Coletar métricas (faz push de commit vazio e aguarda o run)
./collect_metrics.sh <cenário>

# Exemplos de cenários:
./collect_metrics.sh "clean-build"
./collect_metrics.sh "cold-cache"
./collect_metrics.sh "warm-cache"
```

### Jenkins — `collect_jenkins_metrics.sh`

```bash
# Usar o último build já executado
./collect_jenkins_metrics.sh "clean-build"

# Disparar um novo build e coletar (requer JENKINS_USER + JENKINS_TOKEN)
export JENKINS_USER=admin
export JENKINS_TOKEN=<seu-api-token>
./collect_jenkins_metrics.sh "clean-build" --trigger
```

O API token do Jenkins é gerado em:
`http://76.76.112.86:8080/user/admin/configure` → API Token → Add new Token

## Cenários de coleta planejados

| # | Cenário | Descrição | Runs mínimos |
|---|---------|-----------|-------------|
| 1 | `clean-build` | Build padrão sem modificações | 5 |
| 2 | `cold-cache` | Força reinstalação de dependências | 5 |
| 3 | `warm-cache` | Build consecutivo (cache quente) | 5 |
| 4 | `fail-phpstan` | Introduz erro de análise estática | 3 |
| 5 | `fail-phpunit` | Introduz teste falhando | 3 |

## Coleta do baseline atual (Run #18)

Dados do GitHub Actions coletados manualmente em 2026-04-16:

```
run_number : 18
status     : success
total      : 20s
composer   : 3s
phpstan    : 3s
phpcs      : 0s
phpunit    : 4s
```
