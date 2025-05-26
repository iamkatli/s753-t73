// Jenkinsfile (Declarative Pipeline)

pipeline {
    agent any // Or specify a particular agent if needed (e.g., one with Docker)

    environment {
        // Define common environment variables here
        // Replace 'yourdockerhubusername' accordingly
        PHP_IMAGE_PREFIX    = "localhost/s753-t73-php"
        APACHE_IMAGE_PREFIX = "localhost/s753-t73-apache"
        MYSQL_IMAGE_PREFIX  = "localhost/s753-t73-mysql"
        APP_VERSION         = "${env.BUILD_NUMBER ?: 'latest'}" // Use Jenkins build number

        // For Deploy Stage
        TEST_NETWORK_NAME    = "s753-test-network"
        MYSQL_CONTAINER_NAME = "s753-test-mysql"
        PHP_CONTAINER_NAME   = "s753-test-php"
        APACHE_CONTAINER_NAME= "s753-test-apache"
        APACHE_EXPOSED_PORT  = "8080"
        DB_ROOT_PASSWORD     = "password"
        DB_DATABASE          = "mydb"
        DB_USER              = "admin"
        DB_PASSWORD          = "password"

        // For Test Stage
        APP_URL              = "http://localhost:${APACHE_EXPOSED_PORT}"
        INDEX_PAGE_URL       = "${APP_URL}/index.php"
        EXPECTED_TEXT        = "Welcome" // CHANGE THIS!
    }

    stages {
        stage('Checkout') {
            steps {
                echo "INFO: Checking out code..."
                checkout scm // Checks out code from the SCM configured in the Jenkins job
                echo "INFO: Code checkout complete."
            }
        }

        stage('Build Docker Images') {
            steps {
                script {
                    echo "-------------------------------------------------------------------"
                    echo "INFO: Starting Build Stage (using direct docker build commands)"
                    echo "INFO: Jenkins Workspace: ${env.WORKSPACE}"
                    echo "INFO: Jenkins Build Number: ${env.BUILD_NUMBER}"
                    echo "-------------------------------------------------------------------"
                    echo "INFO: Docker App Version for tagging will be: ${APP_VERSION}"
                    // You can copy the content of your existing "Build Stage" shell script here
                    // Ensure image names use the environment variables defined above
                    def phpImageFullName    = "${PHP_IMAGE_PREFIX}:${APP_VERSION}"
                    def apacheImageFullName = "${APACHE_IMAGE_PREFIX}:${APP_VERSION}"
                    def mysqlImageFullName  = "${MYSQL_IMAGE_PREFIX}:${APP_VERSION}"
                    
                    boolean buildSuccess = true

                    echo "INFO: Building PHP image: ${phpImageFullName}..."
                    try {
                        sh "(cd php && docker build -t \"${phpImageFullName}\" .)"
                        echo "SUCCESS: PHP Docker image built."
                    } catch (Exception e) {
                        echo "ERROR: PHP Docker image build FAILED: ${e.getMessage()}"
                        buildSuccess = false
                        currentBuild.result = 'FAILURE'
                    }

                    if (buildSuccess) {
                        echo "INFO: Building Apache image: ${apacheImageFullName}..."
                        try {
                            sh "(cd apache && docker build -t \"${apacheImageFullName}\" .)"
                            echo "SUCCESS: Apache Docker image built."
                        } catch (Exception e) {
                            echo "ERROR: Apache Docker image build FAILED: ${e.getMessage()}"
                            buildSuccess = false
                            currentBuild.result = 'FAILURE'
                        }
                    }
                    
                    if (buildSuccess) {
                        echo "INFO: Building MySQL image: ${mysqlImageFullName}..."
                        try {
                            sh "(cd mysql && docker build -t \"${mysqlImageFullName}\" .)"
                            echo "SUCCESS: MySQL Docker image built."
                        } catch (Exception e) {
                            echo "ERROR: MySQL Docker image build FAILED: ${e.getMessage()}"
                            buildSuccess = false
                            currentBuild.result = 'FAILURE'
                        }
                    }

                    if (!buildSuccess) {
                        error "ERROR: One or more Docker image builds FAILED."
                    } else {
                        echo "INFO: All Docker images built successfully!"
                    }
                    echo "-------------------------------------------------------------------"
                }
            }
        }

        stage('Deploy to Test Environment') {
            steps {
                script {
                    echo "-------------------------------------------------------------------"
                    echo "INFO: Starting Stage: Deploy to Test Environment"
                    
                    def phpImageFullName    = "${env.PHP_IMAGE_PREFIX}:${env.APP_VERSION}"
                    def apacheImageFullName = "${env.APACHE_IMAGE_PREFIX}:${env.APP_VERSION}"
                    def mysqlImageFullName  = "${env.MYSQL_IMAGE_PREFIX}:${env.APP_VERSION}"

                    sh label: 'Deploy Services', script: """
                        set -e # Exit immediately if a command exits with a non-zero status.

                        echo "INFO: Cleaning up any previous test environment..."
                        docker stop "${env.APACHE_CONTAINER_NAME}" "${env.PHP_CONTAINER_NAME}" "${env.MYSQL_CONTAINER_NAME}" > /dev/null 2>&1 || true
                        docker rm "${env.APACHE_CONTAINER_NAME}" "${env.PHP_CONTAINER_NAME}" "${env.MYSQL_CONTAINER_NAME}" > /dev/null 2>&1 || true
                        echo "INFO: Removing MySQL data volume s753-db-data-test for clean user initialization..."
                        docker volume rm s753-db-data-test > /dev/null 2>&1 || true # Ensures MySQL re-initializes the user
                        echo "INFO: Cleanup complete."
                        
                        echo "INFO: Creating Docker network '${env.TEST_NETWORK_NAME}'..."
                        docker network create "${env.TEST_NETWORK_NAME}" > /dev/null 2>&1 || echo "INFO: Network '${env.TEST_NETWORK_NAME}' already exists or error creating."

                        echo "INFO: Starting MySQL container ('${env.MYSQL_CONTAINER_NAME}')..."
                        # Add 'mysqld --default-authentication-plugin=mysql_native_password' at the end
                        docker run -d \\
                            --name "${env.MYSQL_CONTAINER_NAME}" \\
                            --network "${env.TEST_NETWORK_NAME}" \\
                            -e MYSQL_ROOT_PASSWORD="${env.DB_ROOT_PASSWORD}" \\
                            -e MYSQL_DATABASE="${env.DB_DATABASE}" \\
                            -e MYSQL_USER="${env.DB_USER}" \\
                            -e MYSQL_PASSWORD="${env.DB_PASSWORD}" \\
                            -v "s753-db-data-test:/var/lib/mysql" \\
                            "${mysqlImageFullName}" \\
                            mysqld --default-authentication-plugin=mysql_native_password
                        
                        echo "INFO: MySQL container started. Waiting for it to initialize..."
                        # Increased sleep slightly to ensure MySQL has time to fully initialize with the new volume
                        sleep 30 

                        echo "INFO: Starting PHP-FPM container ('${env.PHP_CONTAINER_NAME}')..."
                        docker run -d \\
                            --name "${env.PHP_CONTAINER_NAME}" \\
                            --network "${env.TEST_NETWORK_NAME}" \\
                            # If your PHP app needs specific env vars for DB connection (though it reads from config.php)
                            # -e DB_HOST="${env.MYSQL_CONTAINER_NAME}" \\ # Example if config.php used getenv()
                            "${phpImageFullName}"
                        echo "INFO: PHP-FPM container started."

                        echo "INFO: Starting Apache container ('${env.APACHE_CONTAINER_NAME}')..."
                        docker run -d \\
                            --name "${env.APACHE_CONTAINER_NAME}" \\
                            --network "${env.TEST_NETWORK_NAME}" \\
                            -p "${env.APACHE_EXPOSED_PORT}:80" \\
                            "${apacheImageFullName}"
                        echo "INFO: Apache container started."
                        
                        echo "SUCCESS: Test environment deployed!"
                        echo "Your application should be accessible on: http://localhost:${env.APACHE_EXPOSED_PORT}"
                        echo "Services running:"
                        docker ps --filter network="${env.TEST_NETWORK_NAME}"
                    """
                    echo "-------------------------------------------------------------------"
                }
            }
        }
// ... (rest of Jenkinsfile, e.g., Test Stage, post actions)



        
        // Add other stages here: Code Quality, Security, Release, Monitoring
    }

}