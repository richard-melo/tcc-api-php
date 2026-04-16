// Pipeline Declarativo — Expense API
// Pré-requisito no servidor Jenkins:
//   PHP 8.1+ com extensões: sqlite3, pdo_sqlite, mbstring, pcov (ou xdebug)
//   Composer instalado globalmente (/usr/local/bin/composer)

pipeline {
    agent any

    environment {
        APP_ENV    = 'testing'
        DB_PATH    = ':memory:'
        JWT_SECRET = 'jenkins-ci-secret'
        PCOV_ENABLED = '1'
    }

    stages {

        // ── 1. Checkout ─────────────────────────────────────────────────────
        stage('Checkout do código') {
            steps {
                checkout scm
            }
        }

        // ── 2. Setup PHP ────────────────────────────────────────────────────
        stage('Setup PHP 8.1') {
            steps {
                sh '''
                    php -v
                    php -m | grep -E "sqlite3|pdo_sqlite|mbstring|pcov|xdebug"
                    composer --version
                '''
            }
        }

        // ── 3. Instalar dependências ─────────────────────────────────────────
        stage('Instalar dependências (Composer)') {
            steps {
                sh 'composer install --no-interaction --prefer-dist --no-progress --no-security-blocking'
            }
        }

        // ── 4. Análise estática (PHPStan) ────────────────────────────────────
        stage('Análise estática — PHPStan level 5') {
            steps {
                sh 'vendor/bin/phpstan analyse src/ --level=5 --no-progress'
            }
        }

        // ── 5. Padrões de código (PHPCS) ─────────────────────────────────────
        stage('Padrões de código — PHPCS PSR-12') {
            steps {
                sh 'vendor/bin/phpcs --standard=PSR12 src/'
            }
        }

        // ── 6. Testes automatizados ───────────────────────────────────────────
        stage('Testes automatizados — PHPUnit') {
            steps {
                sh 'vendor/bin/phpunit --no-coverage'
            }
        }

        // ── 7. Armazenar artefato de resultados ───────────────────────────────
        stage('Publicar relatório de cobertura') {
            steps {
                archiveArtifacts artifacts: 'junit.xml', fingerprint: true
            }
        }

    }

    post {
        always {
            // Publica resultados de teste no painel do Jenkins
            junit '**/junit.xml'

            // Limpa workspace após execução
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
