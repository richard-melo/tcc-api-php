// Pipeline Declarativo — Expense API
// Pré-requisito no servidor Jenkins:
//   PHP 8.1+ com extensões: sqlite3, pdo_sqlite, mbstring, pcov
//   Composer instalado globalmente (/usr/local/bin/composer)

pipeline {
    agent any

    environment {
        APP_ENV      = 'testing'
        DB_PATH      = ':memory:'
        JWT_SECRET   = 'jenkins-ci-secret'
        PCOV_ENABLED = '1'
    }

    stages {

        // ── 1. Checkout ──────────────────────────────────────────────────────
        stage('Checkout do código') {
            steps {
                script {
                    def t = System.currentTimeMillis()
                    checkout scm
                    echo "STAGE_TIME_checkout: ${System.currentTimeMillis() - t}"
                }
            }
        }

        // ── 2. Setup PHP ─────────────────────────────────────────────────────
        stage('Setup PHP 8.1') {
            steps {
                script {
                    def t = System.currentTimeMillis()
                    sh '''
                        php -v
                        php -m | grep -E "sqlite3|pdo_sqlite|mbstring|pcov"
                        composer --version
                    '''
                    echo "STAGE_TIME_setup: ${System.currentTimeMillis() - t}"
                }
            }
        }

        // ── 3. Instalar dependências ─────────────────────────────────────────
        stage('Instalar dependências (Composer)') {
            steps {
                script {
                    def t = System.currentTimeMillis()
                    sh 'composer install --no-interaction --prefer-dist --no-progress --no-security-blocking'
                    echo "STAGE_TIME_composer: ${System.currentTimeMillis() - t}"
                }
            }
        }

        // ── 4. Análise estática (PHPStan) ────────────────────────────────────
        stage('Análise estática — PHPStan level 5') {
            steps {
                script {
                    def t = System.currentTimeMillis()
                    sh 'vendor/bin/phpstan analyse src/ --level=5 --no-progress'
                    echo "STAGE_TIME_phpstan: ${System.currentTimeMillis() - t}"
                }
            }
        }

        // ── 5. Padrões de código (PHPCS) ─────────────────────────────────────
        stage('Padrões de código — PHPCS PSR-12') {
            steps {
                script {
                    def t = System.currentTimeMillis()
                    sh 'vendor/bin/phpcs --standard=PSR12 src/'
                    echo "STAGE_TIME_phpcs: ${System.currentTimeMillis() - t}"
                }
            }
        }

        // ── 6. Testes automatizados com cobertura ─────────────────────────────
        stage('Testes automatizados — PHPUnit') {
            steps {
                script {
                    def t = System.currentTimeMillis()
                    sh 'php -d pcov.enabled=1 vendor/bin/phpunit --coverage-clover=coverage.xml --coverage-text'
                    echo "STAGE_TIME_phpunit: ${System.currentTimeMillis() - t}"
                }
            }
        }

        // ── 7. Extrair e publicar cobertura ───────────────────────────────────
        stage('Publicar relatório de cobertura') {
            steps {
                script {
                    def coverage = sh(
                        script: '''php -r "
                            if (!file_exists('coverage.xml')) { echo '0'; exit; }
                            \\$xml = simplexml_load_file('coverage.xml');
                            \\$m   = \\$xml->project->metrics;
                            \\$s   = (int)\\$m['statements'];
                            \\$c   = (int)\\$m['coveredstatements'];
                            echo \\$s > 0 ? round(\\$c / \\$s * 100, 2) : 0;
                        "''',
                        returnStdout: true
                    ).trim()
                    echo "COVERAGE_PERCENT: ${coverage}"
                }
                archiveArtifacts artifacts: 'junit.xml,coverage.xml', fingerprint: true
            }
        }

    }

    post {
        always {
            junit '**/junit.xml'
            cleanWs()
        }
        success {
            echo "Pipeline concluído com sucesso. Todos os testes passaram."
        }
        failure {
            echo "Pipeline falhou. Verifique os logs acima."
        }
    }
}
