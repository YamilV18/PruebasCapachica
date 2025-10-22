pipeline {
    agent {
        docker {
            image 'php:8.3-cli'
            // Opcional: Montar el directorio de Composer para acelerar descargas futuras
            args '-v $HOME/.composer:/var/jenkins_home/.composer' 
        }
    }

    environment {
        APP_ENV = 'testing'
        APP_DEBUG = 'true'

        DB_CONNECTION = 'mysql'
        DB_HOST = '192.168.31.233'
        DB_PORT = '3306'
        DB_DATABASE = 'turismobackend_test'
        DB_USERNAME = 'nick'
        DB_PASSWORD = 'nick123'

        SONARQUBE_ENV = 'Sonarqube'
    }

    stages {
        stage('Clone') {
            steps {
                timeout(time: 2, unit: 'MINUTES') {
                    git branch: 'main', credentialsId: 'githubtoken2', url: 'https://github.com/Henyelrey/PruebasCapachica.git'
                }
            }
        }

        stage('Install Dependencies') {
            steps {
                timeout(time: 5, unit: 'MINUTES') {
                    sh '''
                        echo "Instalando dependencias con Composer..."
                        composer self-update
                        composer install --no-interaction --prefer-dist --optimize-autoloader
                    '''
                }
            }
        }

        stage('Build') {
            steps {
                echo "Optimizando cachés y configuraciones de Laravel..."
                sh '''
                    php artisan config:clear
                    php artisan cache:clear
                    php artisan route:clear
                    php artisan view:clear
                    php artisan config:cache
                '''
            }
        }

        stage('Test') {
            steps {
                timeout(time: 10, unit: 'MINUTES') {
                    echo "Ejecutando pruebas PHPUnit..."
                    sh '''
                        ./vendor/bin/phpunit --configuration phpunit.xml --testdox
                    '''
                }
            }
            post {
                always {
                    // Si generas reportes JUnit, Jenkins los recogerá aquí
                    junit 'tests/_reports/*.xml'
                }
            }
        }

        stage('SonarQube Analysis') {
            when {
                expression { return fileExists('sonar-project.properties') }
            }
            steps {
                timeout(time: 5, unit: 'MINUTES') {
                    withSonarQubeEnv("${SONARQUBE_ENV}") {
                        echo "Ejecutando análisis de SonarQube..."
                        sh 'sonar-scanner'
                    }
                }
            }
        }

        stage('Quality Gate') {
            when {
                expression { return fileExists('sonar-project.properties') }
            }
            steps {
                echo "Esperando validación de calidad de código..."
                sleep(10)
                timeout(time: 4, unit: 'MINUTES') {
                    waitForQualityGate abortPipeline: true
                }
            }
        }

        stage('Deploy') {
            steps {
                timeout(time: 8, unit: 'MINUTES') {
                    echo "Desplegando aplicación Laravel Capachica..."
                    sh '''
                        php artisan migrate --force
                        php artisan serve --host=0.0.0.0 --port=8000 &
                    '''
                }
            }
        }
    }

    post {
        success {
            echo "✅ Pipeline 'Capachica' ejecutado correctamente."
        }
        failure {
            echo "❌ Error en el pipeline 'Capachica'."
        }
    }
}
