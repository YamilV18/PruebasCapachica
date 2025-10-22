pipeline {
    // 1. CAMBIO PRINCIPAL: Usamos un agente Docker con PHP y Composer
    agent {
        docker {
            image 'php:8.3-cli'
            // Opcional: Montar el directorio de Composer para acelerar descargas futuras
            args '-v $HOME/.composer:/var/jenkins_home/.composer' 
        }
    }

    // Usamos 'withCredentials' para manejar credenciales sensibles en lugar de environment global,
    // pero mantengo tus variables globales para la configuración del proyecto.
    environment {
        APP_ENV = 'testing'
        APP_DEBUG = 'true'

        DB_CONNECTION = 'mysql'
        DB_HOST = '192.168.31.233'
        DB_PORT = '3306'
        DB_DATABASE = 'turismobackend_test'
        DB_USERNAME = 'nick'
        DB_PASSWORD = 'nick123'
        
        // SonarQube credenciales (deberías usar Secrets/Credentials aquí)
        SONARQUBE_ENV = 'Sonarqube' 
    }

    stages {
        stage('Clone') {
            steps {
                timeout(time: 2, unit: 'MINUTES') {
                    // La instalación del git en el agente Docker es más rápida y fiable
                    sh 'apk add --no-cache git' 
                    git branch: 'main', credentialsId: 'githubtoken2', url: 'https://github.com/Henyelrey/PruebasCapachica.git'
                }
            }
        }
        
        stage('Configuration') {
            steps {
                echo "Creando .env y generando la clave de aplicación de Laravel..."
                sh '''
                    # Crear archivo .env a partir de las variables de entorno de Jenkins
                    echo "APP_ENV=${APP_ENV}" > .env
                    echo "APP_DEBUG=${APP_DEBUG}" >> .env
                    echo "DB_CONNECTION=${DB_CONNECTION}" >> .env
                    echo "DB_HOST=${DB_HOST}" >> .env
                    echo "DB_PORT=${DB_PORT}" >> .env
                    echo "DB_DATABASE=${DB_DATABASE}" >> .env
                    echo "DB_USERNAME=${DB_USERNAME}" >> .env
                    echo "DB_PASSWORD=${DB_PASSWORD}" >> .env
                    
                    # Generar la clave de aplicación. NECESARIO antes de las pruebas.
                    php artisan key:generate
                '''
            }
        }

        stage('Install Dependencies') {
            steps {
                timeout(time: 5, unit: 'MINUTES') {
                    echo "Instalando dependencias con Composer..."
                    // Removido 'composer self-update' para evitar problemas de permisos/caché en el contenedor
                    sh 'composer install --no-interaction --prefer-dist --optimize-autoloader'
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
                    # Instalación de dependencias de Node/NPM si se usa, pero lo dejo comentado por ahora:
                    # npm install
                    # npm run build 
                '''
            }
        }

        stage('Test') {
            steps {
                timeout(time: 10, unit: 'MINUTES') {
                    echo "Ejecutando pruebas PHPUnit..."
                    sh './vendor/bin/phpunit --configuration phpunit.xml --testdox --log-junit tests/report.xml'
                }
            }
            post {
                always {
                    // Recoge el reporte de JUnit para mostrarlo en Jenkins
                    junit 'tests/report.xml' 
                }
            }
        }

        stage('SonarQube Analysis') {
             // NOTA: Si 'sonar-scanner' no está disponible en la imagen 'php:8.3-cli',
             // es posible que debas instalarlo dentro de este stage o usar un agente diferente.
             // Pero lo mantendremos así asumiendo que el plugin lo gestiona correctamente.
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
                // Quitamos el sleep y usamos directamente el timeout de la Quality Gate
                timeout(time: 4, unit: 'MINUTES') { 
                    waitForQualityGate abortPipeline: true
                }
            }
        }

        stage('Deploy') {
            steps {
                timeout(time: 8, unit: 'MINUTES') {
                    echo "Aplicando migraciones y ejecutando servidor de prueba..."
                    sh '''
                        php artisan migrate --force
                        # ADVERTENCIA: Este comando ('php artisan serve') solo inicia un servidor de desarrollo.
                        # El servidor se detendrá cuando el job de Jenkins termine o si el contenedor se cierra.
                        # Para un despliegue real, deberías usar un servidor web (como Nginx/Apache)
                        # o una herramienta de despliegue (como SSH/Deployer/Capistrano).
                        php artisan serve --host=0.0.0.0 --port=8000 &
                        
                        # Esperar un poco para que el servidor inicie (opcional)
                        sleep 5 
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
            echo "❌ Error en el pipeline 'Capachica'. Revisa los logs de 'Install Dependencies'."
        }
    }
}