// Jenkinsfile (Declarative Pipeline)

pipeline {
    agent any // Or specify a particular agent if needed (e.g., one with Docker)

    environment {
        // Define common environment variables here
        // Replace 'yourdockerhubusername' accordingly
        LOCAL_IMAGE_REPO    = "localhost"
        PHP_IMAGE           = "s753-t73-php"
        APACHE_IMAGE        = "s753-t73-apache"
        MYSQL_IMAGE         = "s753-t73-mysql"
        PHP_IMAGE_PREFIX    = "${LOCAL_IMAGE_REPO}/${PHP_IMAGE}"
        APACHE_IMAGE_PREFIX = "${LOCAL_IMAGE_REPO}/${APACHE_IMAGE}"
        MYSQL_IMAGE_PREFIX  = "${LOCAL_IMAGE_REPO}/${MYSQL_IMAGE}"
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

        // For release stage
        DOCKERHUB_USER       = "docker1010"
        
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
                    
                    try {
                        // Put the entire docker run command on a single line
                        sh """
                        docker run --rm -e SONAR_HOST_URL="${env.SONAR_HOST_URL_ENV}" -e SONAR_TOKEN="${env.SONAR_TOKEN_ENV}" -v "${env.WORKSPACE}:/usr/src" sonarsource/sonar-scanner-cli
                        """
                        // Note: If SONAR_TOKEN_ENV is managed by Jenkins credentials, it would be injected differently,
                        // e.g., within a withCredentials block. For now, using the environment variable as you have it.

                        echo "SUCCESS: SonarQube analysis submitted. Check SonarQube dashboard."
                    } catch (Exception e) {
                        echo "ERROR: SonarQube analysis FAILED: ${e.getMessage()}"
                        // Decide if you want to fail the pipeline if analysis fails
                        currentBuild.result = 'FAILURE' // This will mark the stage/build as failed
                        error "SonarQube analysis failed" // This will stop the pipeline
                    }
                    echo "-------------------------------------------------------------------"
                }
            }
        }

        // Jenkinsfile
// ... (environment block and previous stages: Checkout, Build, Deploy, Test, Code Quality) ...

        stage('Security Analysis & Approval') { // Renamed for clarity
            steps {
                script {
                    echo "-------------------------------------------------------------------"
                    echo "INFO: Starting Stage: Security Analysis & Approval"
                    echo "INFO: The SonarQube scan has completed in the previous 'Code Quality Analysis' stage."
                    echo "INFO: Please review the SonarQube dashboard for security vulnerabilities and hotspots at:"
                    echo "INFO: ${env.SONAR_HOST_URL_ENV}/dashboard?id=s753-t73" // Ensure s753-t73 is your sonar.projectKey

                    def userInput
                    try {
                        // Pause the pipeline and wait for manual input.
                        // The timeout prevents the pipeline from waiting indefinitely.
                        userInput = timeout(time: 2, unit: 'HOURS') {
                            input(
                                id: 'securityApproval', // Optional ID for the input
                                message: "Security Review Gate: Please check the SonarQube report at ${env.SONAR_HOST_URL_ENV}/dashboard?id=s753-t73. Do you approve to continue?",
                                parameters: [[$class: 'BooleanParameterDefinition', defaultValue: true, description: '', name: 'Proceed with approval?']]
                                // ok: "Proceed" // Optional: Custom text for the 'Proceed' button
                                // submitter: 'jenkins_user_id,another_user_id', // Optional: Comma-separated list of Jenkins user IDs or group names who can approve.
                                                                               // If omitted, anyone with Build permission on the job can approve.
                                // submitterParameter: 'APPROVER_USER_ID' // Optional: Environment variable to store the approver's ID
                            )
                        }
                        // If 'Proceed' is clicked, userInput will not be null (it might contain parameters if you defined any).
                        echo "INFO: Security review approved. Proceeding with pipeline."
                        // if (userInput.APPROVER_USER_ID) { // If you used submitterParameter
                        //     echo "INFO: Approved by: ${userInput.APPROVER_USER_ID}"
                        // }

                    } catch (org.jenkinsci.plugins.workflow.steps.FlowInterruptedException e) {
                        // This catch block handles if the input is manually "Aborted" by a user,
                        // or if the timeout is reached.
                        echo "ERROR: Security Review was aborted or timed out."
                        currentBuild.result = 'ABORTED' // Set the build status
                        error "Pipeline aborted at Security Review Gate." // Stops the pipeline and marks it as failed/aborted
                    }
                    
                    echo "INFO: Security Analysis & Approval stage complete."
                    echo "-------------------------------------------------------------------"
                }
            }
        }

        stage('Release') {
            steps {
                script {
                    echo "-------------------------------------------------------------------"
                    echo "INFO: Starting Stage: Release"

                    // Ensure DOCKERHUB_USERNAME is set in your environment block or directly here
                    // def dockerHubUsername = "yourdockerhubusername" // Or from env.DOCKERHUB_USERNAME
                    // Ensure you have your DOCKERHUB_CREDENTIALS_ID from Jenkins credentials
                    def dockerHubCredentialsId = 'dockerhub-credentials' // The ID you gave your Docker Hub creds in Jenkins

                    // Define the release tag (e.g., using the build number or a semantic version)
                    def releaseTag = "release-${env.APP_VERSION}" // Example: release-17

                    // Image names built in previous stages
                    def localPhpImageFullName    = "${env.PHP_IMAGE_PREFIX}:${env.APP_VERSION}"
                    def localApacheImageFullName = "${env.APACHE_IMAGE_PREFIX}:${env.APP_VERSION}"
                    def localMysqlImageFullName  = "${env.MYSQL_IMAGE_PREFIX}:${env.APP_VERSION}"

                    // Define target image names for Docker Hub (replace 'yourdockerhubusername')
                    def targetPhpImage    = "${env.DOCKERHUB_USER}/${env.PHP_IMAGE}:${releaseTag}"
                    def targetApacheImage = "${env.DOCKERHUB_USER}/${env.APACHE_IMAGE}:${releaseTag}"
                    def targetMysqlImage  = "${env.DOCKERHUB_USER}/${env.MYSQL_IMAGE}:${releaseTag}"
                    // Note: .split('/')[1] is used to get the 's753-t73-php' part if IMAGE_PREFIX is 'localhost/s753-t73-php'

                    echo "INFO: Release Tag: ${releaseTag}"
                    echo "INFO: Target PHP Image for Docker Hub: ${targetPhpImage}"
                    echo "INFO: Target Apache Image for Docker Hub: ${targetApacheImage}"
                    echo "INFO: Target MySQL Image for Docker Hub: ${targetMysqlImage}"

                    // Use 'withCredentials' to securely access Docker Hub credentials
                    withCredentials([usernamePassword(credentialsId: dockerHubCredentialsId, usernameVariable: 'DOCKER_USER', passwordVariable: 'DOCKER_PASS')]) {
                        try {
                            echo "INFO: Logging in to Docker Hub..."
                            sh "docker login -u \"${DOCKER_USER}\" -p \"${DOCKER_PASS}\""

                            echo "INFO: Re-tagging and Pushing PHP image..."
                            sh "docker tag \"${localPhpImageFullName}\" \"${targetPhpImage}\""
                            sh "docker push \"${targetPhpImage}\""

                            echo "INFO: Re-tagging and Pushing Apache image..."
                            sh "docker tag \"${localApacheImageFullName}\" \"${targetApacheImage}\""
                            sh "docker push \"${targetApacheImage}\""
                            
                            echo "INFO: Re-tagging and Pushing MySQL image..."
                            sh "docker tag \"${localMysqlImageFullName}\" \"${targetMysqlImage}\""
                            sh "docker push \"${targetMysqlImage}\""

                            echo "SUCCESS: All images pushed to Docker Hub."

                            // (Optional) Git tagging
                            echo "INFO: Tagging Git commit for release..."
                            // Ensure Git user is configured if Jenkins runs as a generic user
                            // sh "git config --global user.email 'jenkins@example.com'"
                            // sh "git config --global user.name 'Jenkins CI'"
                            sh "git tag -a \"${releaseTag}\" -m \"Release ${releaseTag}\""
                            sh "git push origin \"${releaseTag}\"" // Push the tag to remote
                            echo "SUCCESS: Git commit tagged and pushed as ${releaseTag}."

                        } catch (Exception e) {
                            echo "ERROR: Release stage failed: ${e.getMessage()}"
                            currentBuild.result = 'FAILURE'
                            error "Release stage failed."
                        } finally {
                            echo "INFO: Logging out from Docker Hub..."
                            sh "docker logout" // Good practice to logout
                        }
                    }
                    echo "-------------------------------------------------------------------"
                }
            }
        }
    }

}