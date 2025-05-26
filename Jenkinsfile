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
                    // Copy the content of your "Deploy to Test Environment" shell script here
                    // Use the environment variables defined above
                    def phpImageFullName    = "${PHP_IMAGE_PREFIX}:${APP_VERSION}"
                    def apacheImageFullName = "${APACHE_IMAGE_PREFIX}:${APP_VERSION}"
                    def mysqlImageFullName  = "${MYSQL_IMAGE_PREFIX}:${APP_VERSION}"

                    sh """
                        echo "INFO: Cleaning up any previous test environment..."
                        docker stop "${APACHE_CONTAINER_NAME}" "${PHP_CONTAINER_NAME}" "${MYSQL_CONTAINER_NAME}" > /dev/null 2>&1 || true
                        docker rm "${APACHE_CONTAINER_NAME}" "${PHP_CONTAINER_NAME}" "${MYSQL_CONTAINER_NAME}" > /dev/null 2>&1 || true
                        echo "INFO: Cleanup complete."
                        
                        echo "INFO: Creating Docker network '${TEST_NETWORK_NAME}'..."
                        docker network create "${TEST_NETWORK_NAME}" > /dev/null 2>&1 || echo "INFO: Network '${TEST_NETWORK_NAME}' already exists or error creating."

                        echo "INFO: Starting MySQL container ('${MYSQL_CONTAINER_NAME}')..."
                        docker run -d \\
                            --name "${MYSQL_CONTAINER_NAME}" \\
                            --network "${TEST_NETWORK_NAME}" \\
                            -e MYSQL_ROOT_PASSWORD="${DB_ROOT_PASSWORD}" \\
                            -e MYSQL_DATABASE="${DB_DATABASE}" \\
                            -e MYSQL_USER="${DB_USER}" \\
                            -e MYSQL_PASSWORD="${DB_PASSWORD}" \\
                            -v "s753-db-data-test:/var/lib/mysql" \\
                            "${mysqlImageFullName}"
                        
                        # Basic check (can be improved with a loop)
                        if [ \$? -ne 0 ]; then echo "ERROR: Failed to start MySQL container."; currentBuild.result = 'FAILURE'; error("MySQL start failed"); fi
                        echo "INFO: MySQL container started. Waiting for it to initialize..."
                        sleep 20

                        echo "INFO: Starting PHP-FPM container ('${PHP_CONTAINER_NAME}')..."
                        docker run -d \\
                            --name "${PHP_CONTAINER_NAME}" \\
                            --network "${TEST_NETWORK_NAME}" \\
                            "${phpImageFullName}"
                        if [ \$? -ne 0 ]; then echo "ERROR: Failed to start PHP-FPM container."; currentBuild.result = 'FAILURE'; error("PHP-FPM start failed"); fi
                        echo "INFO: PHP-FPM container started."

                        echo "INFO: Starting Apache container ('${APACHE_CONTAINER_NAME}')..."
                        docker run -d \\
                            --name "${APACHE_CONTAINER_NAME}" \\
                            --network "${TEST_NETWORK_NAME}" \\
                            -p "${APACHE_EXPOSED_PORT}:80" \\
                            "${apacheImageFullName}"
                        if [ \$? -ne 0 ]; then echo "ERROR: Failed to start Apache container."; currentBuild.result = 'FAILURE'; error("Apache start failed"); fi
                        echo "INFO: Apache container started."
                        
                        echo "SUCCESS: Test environment deployed!"
                        echo "Your application should be accessible on: http://localhost:${APACHE_EXPOSED_PORT}"
                        docker ps --filter network="${TEST_NETWORK_NAME}"
                    """
                    echo "-------------------------------------------------------------------"
                }
            }
        }

        stage('Automated Tests (Smoke Test)') {
            steps {
                script {
                    echo "-------------------------------------------------------------------"
                    echo "INFO: Starting Stage: Automated Tests (Smoke Test)"
                    // Copy the content of your "Test Stage" (smoke test) shell script here
                    // Use the environment variables
                    sh """
                        EXPECTED_HTTP_CODE="200"
                        TEST_SUCCESS=true

                        echo "INFO: Test 1: Checking accessibility of ${INDEX_PAGE_URL}"
                        HTTP_CODE=\$(curl -s -o /dev/null -w "%{http_code}" "${INDEX_PAGE_URL}")

                        if [ "\${HTTP_CODE}" -eq "\${EXPECTED_HTTP_CODE}" ]; then
                            echo "SUCCESS: Index page returned HTTP \${HTTP_CODE}."
                        else
                            echo "ERROR: Index page returned HTTP \${HTTP_CODE}. Expected \${EXPECTED_HTTP_CODE}."
                            TEST_SUCCESS=false
                        fi

                        if [ "\${TEST_SUCCESS}" = true ]; then
                            echo "INFO: Test 2: Checking content of ${INDEX_PAGE_URL} for text: '${EXPECTED_TEXT}'"
                            if curl -s -L "${INDEX_PAGE_URL}" | grep -q "${EXPECTED_TEXT}"; then
                                echo "SUCCESS: Expected text '${EXPECTED_TEXT}' found on the index page."
                            else
                                echo "ERROR: Expected text '${EXPECTED_TEXT}' NOT found on the index page."
                                TEST_SUCCESS=false
                            fi
                        fi

                        if [ "\${TEST_SUCCESS}" = true ]; then
                            echo "SUCCESS: All smoke tests passed!"
                        else
                            echo "ERROR: One or more smoke tests FAILED."
                            currentBuild.result = 'FAILURE' 
                            error "Smoke tests failed"
                        fi
                    """
                    echo "-------------------------------------------------------------------"
                }
            }
        }
        
        // Add other stages here: Code Quality, Security, Release, Monitoring
    }

    post {
        // Runs after all stages, regardless of outcome
        always {
            echo "INFO: Pipeline finished. Cleaning up test containers..."
            // Add cleanup commands for the deployed test containers if needed,
            // though the Deploy stage already has a cleanup for previous runs.
            // If you want to ensure cleanup even on pipeline failure:
            sh """
                docker stop "${APACHE_CONTAINER_NAME}" "${PHP_CONTAINER_NAME}" "${MYSQL_CONTAINER_NAME}" > /dev/null 2>&1 || true
                docker rm "${APACHE_CONTAINER_NAME}" "${PHP_CONTAINER_NAME}" "${MYSQL_CONTAINER_NAME}" > /dev/null 2>&1 || true
                echo "INFO: Post-pipeline cleanup attempt complete."
            """
            // cleanWs() // Cleans up the Jenkins workspace
        }
        success {
            echo "SUCCESS: Pipeline completed successfully!"
        }
        failure {
            echo "FAILURE: Pipeline failed. See logs for details."
        }
    }
}