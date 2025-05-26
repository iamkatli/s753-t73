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
        EXPECTED_TEXT        = "Login Page of ABC Portal"

        // For Code Quality Stage
        SONAR_SERVER         = "10.119.10.137"
        SONAR_EXPOSED_PORT   = "9002"
        SONAR_HOST_URL_ENV   = "http://${SONAR_SERVER}:${SONAR_EXPOSED_PORT}"
        SONAR_TOKEN_ENV      = "sqa_1bff1a3237a77b40451a377f0f59c30443dcadc7"
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
                        sh "(docker build -t \"${phpImageFullName}\" -f php/Dockerfile .)"
                        echo "SUCCESS: PHP Docker image built."
                    } catch (Exception e) {
                        echo "ERROR: PHP Docker image build FAILED: ${e.getMessage()}"
                        buildSuccess = false
                        currentBuild.result = 'FAILURE'
                    }

                    if (buildSuccess) {
                        echo "INFO: Building Apache image: ${apacheImageFullName}..."
                        try {
                            sh "(docker build -t \"${apacheImageFullName}\" -f apache/Dockerfile .)"
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
                        docker volume rm s753-db-data-test > /dev/null 2>&1 || true 
                        echo "INFO: Cleanup complete."
                        
                        echo "INFO: Creating Docker network '${env.TEST_NETWORK_NAME}'..."
                        docker network create "${env.TEST_NETWORK_NAME}" > /dev/null 2>&1 || echo "INFO: Network '${env.TEST_NETWORK_NAME}' already exists or error creating."

                        echo "INFO: Starting MySQL container ('${env.MYSQL_CONTAINER_NAME}')..."
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
                        sleep 30 

                        echo "INFO: Starting PHP-FPM container ('${env.PHP_CONTAINER_NAME}')..."
                        # Ensure this command is clean and the image name is the last argument on its own line (or properly continued)
                        docker run -d \\
                            --name "${env.PHP_CONTAINER_NAME}" \\
                            --network "${env.TEST_NETWORK_NAME}" \\
                            "${phpImageFullName}"  # <-- PHP image name
                        echo "INFO: PHP-FPM container started."

                        echo "INFO: Starting Apache container ('${env.APACHE_CONTAINER_NAME}')..."
                        docker run -d \\
                            --name "${env.APACHE_CONTAINER_NAME}" \\
                            --network "${env.TEST_NETWORK_NAME}" \\
                            -p "${env.APACHE_EXPOSED_PORT}:80" \\
                            "${apacheImageFullName}" # <-- Apache image name
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
        
// Jenkinsfile
// ... (environment block and previous stages: Checkout, Build Docker Images, Deploy, Test) ...

        stage('Code Quality Analysis') {
            steps {
                script {
                    echo "-------------------------------------------------------------------"
                    echo "INFO: Starting Stage: Code Quality Analysis with SonarQube"
                    
                    // Ensure sonar-project.properties is present (it's checked in with your code)
                    // The SonarQube server should be running and accessible at the URL
                    // specified in sonar-project.properties (e.g., http://localhost:9000)

                    // Define SonarQube server URL and token if not in properties file or to override
                    // For this example, we assume it's in sonar-project.properties
                    // def sonarqubeServer = 'http://localhost:9000' // Or from env variable
                    // def sonarqubeToken = 'your_sonarqube_user_token' // Use Jenkins credentials for this!

                    // Run SonarScanner using its Docker image
                    // This mounts the current Jenkins workspace (your project code) into the scanner container
                    // The scanner will pick up the sonar-project.properties file from the workspace root.
                    try {
                        // Make sure the SonarQube server (e.g., running in Docker on port 9000)
                        // is accessible from where this Jenkins pipeline runs.
                        // If Jenkins itself is a Docker container, network configuration is important.
                        // If Jenkins runs on the host and SonarQube is a Docker container on the same host,
                        // localhost:9000 for sonar.host.url should generally work for the scanner running on the host.
                        // If scanner runs as Docker, then host.docker.internal or IP might be needed for sonar.host.url.
                        // For simplicity, this example uses a common SonarScanner Docker image.
                        
                        // If SonarQube server is running as a Docker container named 'sonarqube' and Jenkins
                        // agent can access Docker network, you might need to adjust sonar.host.url
                        // or run the scanner on the same network.
                        // Easiest if sonar.host.url in sonar-project.properties points to an accessible IP/hostname.

                        sh """
                           docker run \\
                               --rm \\
                               -e SONAR_HOST_URL="${env.SONAR_HOST_URL_ENV}" \\
                               -e SONAR_TOKEN="${env.SONAR_TOKEN_ENV}" \\ 
                               -v "${env.WORKSPACE}:/usr/src" \\
                               sonarsource/sonar-scanner-cli
                        """
                        // Note: SONAR_HOST_URL_ENV and SONAR_TOKEN_ENV would be Jenkins environment variables
                        // you set up, possibly from credentials. For now, assuming sonar-project.properties has the URL.
                        // If sonar.login token is in sonar-project.properties, no SONAR_LOGIN env var needed here.

                        echo "SUCCESS: SonarQube analysis submitted. Check SonarQube dashboard."
                    } catch (Exception e) {
                        echo "ERROR: SonarQube analysis FAILED: ${e.getMessage()}"
                        // Decide if you want to fail the pipeline if analysis fails
                        // currentBuild.result = 'FAILURE'
                        // error "SonarQube analysis failed"
                    }
                    echo "-------------------------------------------------------------------"
                }
            }
        }

// ... (post actions) ...
    }

}