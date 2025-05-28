// Jenkinsfile (Declarative Pipeline)

pipeline {
    agent any

    environment {
        // --- General Build Vars ---
        LOCAL_IMAGE_REPO           = "localhost"
        PHP_APP_IMAGE_BASE_NAME    = "s753-t73-php"
        APACHE_APP_IMAGE_BASE_NAME = "s753-t73-apache"
        MYSQL_APP_IMAGE_BASE_NAME  = "s753-t73-mysql"
        
        PHP_IMAGE_PREFIX           = "${LOCAL_IMAGE_REPO}/${PHP_APP_IMAGE_BASE_NAME}"
        APACHE_IMAGE_PREFIX        = "${LOCAL_IMAGE_REPO}/${APACHE_APP_IMAGE_BASE_NAME}"
        MYSQL_IMAGE_PREFIX         = "${LOCAL_IMAGE_REPO}/${MYSQL_APP_IMAGE_BASE_NAME}"
        APP_VERSION                = "${env.BUILD_NUMBER ?: 'latest'}"

        // --- Test Environment Docker Vars ---
        TEST_NETWORK_NAME          = "s753-test-network"
        TEST_MYSQL_CONTAINER_NAME  = "s753-test-mysql"         // Specific MySQL container name for test
        TEST_PHP_FPM_SERVICE_NAME  = "php-fpm-service"         // Generic name PHP container will take in test, matches Apache conf
        TEST_APACHE_CONTAINER_NAME = "s753-test-apache"
        TEST_APACHE_EXPOSED_PORT   = "8080"
        // DB Connection Vars for Test PHP Container
        TEST_DB_HOSTNAME           = "${TEST_MYSQL_CONTAINER_NAME}" // PHP in test connects to this
        TEST_DB_USERNAME           = "admin"
        TEST_DB_PASSWORD           = "password"
        TEST_DB_NAME               = "mydb"

        // --- Production Environment K8s (Minikube) Vars ---
        PROD_K8S_NAMESPACE         = "s753-production"        // Optional: Deploy to a specific K8s namespace
        PROD_MYSQL_K8S_SVC_NAME    = "mysql-prod-svc"         // K8s Service name for MySQL in Prod
        PROD_PHP_FPM_K8S_SVC_NAME  = "php-fpm-service"        // K8s Service name for PHP in Prod (matches Apache conf)
        PROD_APACHE_K8S_SVC_NAME   = "s753-apache-prod-svc"
        PROD_APACHE_NODE_PORT      = "30080"                  // Example NodePort for K8s Apache service
        // DB Connection Vars for Production PHP Pods (via K8s Deployment env vars)
        PROD_DB_HOSTNAME           = "${PROD_MYSQL_K8S_SVC_NAME}"  // PHP in prod connects to this K8s service
        PROD_DB_USERNAME           = "prod_admin"             // Example: use different creds for prod
        PROD_DB_PASSWORD           = "prod_secure_password"   // Store in K8s Secrets in a real scenario!
        PROD_DB_NAME               = "mydb_production"        // Example: different DB name for prod
        
        // --- Shared MySQL Root Password (for initial MySQL container setup in both envs) ---
        DB_ROOT_PASSWORD           = "password" 

        // --- Smoke Test Vars ---
        EXPECTED_TEXT              = "Login Page of ABC Portal" // From your index.php

        // --- Code Quality Stage Vars ---
        SONAR_SERVER               = "10.119.10.137"
        SONAR_EXPOSED_PORT         = "9002"
        SONAR_HOST_URL_ENV         = "http://${SONAR_SERVER}:${SONAR_EXPOSED_PORT}"
        SONAR_PROJECT_KEY          = "s753-t73" // Should match your sonar-project.properties
        SONAR_TOKEN_ENV            = "sqa_1bff1a3237a77b40451a377f0f59c30443dcadc7"

        // --- Release Stage Vars ---
        DOCKERHUB_USER             = "docker1010"
        DOCKERHUB_CREDENTIALS_ID   = "dockerhub-credentials" // Jenkins Credential ID

        // --- Monitor Stage Vars ---
        PROMETHEUS_NAMESPACE    = "monitoring" // Namespace to install Prometheus into
        PROMETHEUS_RELEASE_NAME = "prometheus" // Helm release name
        PROMETHEUS_NODE_PORT    = "30090"      // Host port to access Prometheus UI
    }

    stages {
        stage('Checkout') {
            steps {
                echo "INFO: Checking out code..."
                checkout scm
                echo "INFO: Code checkout complete."
            }
        }

        stage('Build Docker Images') {
            steps {
                script {
                    echo "-------------------------------------------------------------------"
                    echo "INFO: Starting Build Stage"
                    echo "INFO: Jenkins Workspace: ${env.WORKSPACE}"
                    echo "INFO: App Version for tagging will be: ${APP_VERSION}"

                    def phpImageFullName    = "${env.PHP_IMAGE_PREFIX}:${env.APP_VERSION}"
                    def apacheImageFullName = "${env.APACHE_IMAGE_PREFIX}:${env.APP_VERSION}"
                    def mysqlImageFullName  = "${env.MYSQL_IMAGE_PREFIX}:${env.APP_VERSION}"
                    boolean buildSuccess = true

                    echo "INFO: Building PHP image: ${phpImageFullName}..."
                    try {
                        // IMPORTANT: Your php/Dockerfile COPY command needs to be relative to project root,
                        // e.g., COPY php/public /var/www/html/ (since build context is '.')
                        sh "docker build -t \"${phpImageFullName}\" -f php/Dockerfile ."
                        echo "SUCCESS: PHP Docker image built."
                    } catch (Exception e) {
                        echo "ERROR: PHP Docker image build FAILED: ${e.getMessage()}"
                        buildSuccess = false; currentBuild.result = 'FAILURE'
                    }

                    if (buildSuccess) {
                        echo "INFO: Building Apache image: ${apacheImageFullName}..."
                        try {
                            // IMPORTANT: Your apache/Dockerfile COPY command needs to be relative to project root,
                            // e.g., COPY php/public /var/www/html/ (if DocumentRoot is /var/www/html)
                            // AND COPY apache/apache_php.conf /usr/local/apache2/conf/apache_php.conf
                            sh "docker build -t \"${apacheImageFullName}\" -f apache/Dockerfile ."
                            echo "SUCCESS: Apache Docker image built."
                        } catch (Exception e) {
                            echo "ERROR: Apache Docker image build FAILED: ${e.getMessage()}"
                            buildSuccess = false; currentBuild.result = 'FAILURE'
                        }
                    }
                    
                    if (buildSuccess) {
                        echo "INFO: Building MySQL image: ${mysqlImageFullName}..."
                        try {
                            // This build context is 'mysql/', so COPY dump.sql in mysql/Dockerfile is fine.
                            sh "(cd mysql && docker build -t \"${mysqlImageFullName}\" .)"
                            echo "SUCCESS: MySQL Docker image built."
                        } catch (Exception e) {
                            echo "ERROR: MySQL Docker image build FAILED: ${e.getMessage()}"
                            buildSuccess = false; currentBuild.result = 'FAILURE'
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

                    sh label: 'Deploy Test Services', script: """
                        set -e 

                        echo "INFO: Cleaning up any previous test environment..."
                        docker stop "${env.TEST_APACHE_CONTAINER_NAME}" "${env.TEST_PHP_FPM_SERVICE_NAME}" "${env.TEST_MYSQL_CONTAINER_NAME}" > /dev/null 2>&1 || true
                        docker rm "${env.TEST_APACHE_CONTAINER_NAME}" "${env.TEST_PHP_FPM_SERVICE_NAME}" "${env.TEST_MYSQL_CONTAINER_NAME}" > /dev/null 2>&1 || true
                        echo "INFO: Removing TEST MySQL data volume s753-db-data-test..."
                        docker volume rm s753-db-data-test > /dev/null 2>&1 || true 
                        echo "INFO: Cleanup complete."
                        
                        echo "INFO: Creating Docker network '${env.TEST_NETWORK_NAME}'..."
                        docker network create "${env.TEST_NETWORK_NAME}" > /dev/null 2>&1 || echo "INFO: Network '${env.TEST_NETWORK_NAME}' already exists."

                        echo "INFO: Starting TEST MySQL container ('${env.TEST_MYSQL_CONTAINER_NAME}')..."
                        docker run -d \\
                            --name "${env.TEST_MYSQL_CONTAINER_NAME}" \\
                            --network "${env.TEST_NETWORK_NAME}" \\
                            -e MYSQL_ROOT_PASSWORD="${env.DB_ROOT_PASSWORD}" \\
                            -e MYSQL_DATABASE="${env.TEST_DB_NAME}" \\
                            -e MYSQL_USER="${env.TEST_DB_USERNAME}" \\
                            -e MYSQL_PASSWORD="${env.TEST_DB_PASSWORD}" \\
                            -v "s753-db-data-test:/var/lib/mysql" \\
                            "${mysqlImageFullName}" \\
                            mysqld --default-authentication-plugin=mysql_native_password
                        echo "INFO: TEST MySQL container started. Waiting..."
                        sleep 30 

                        echo "INFO: Starting TEST PHP-FPM container ('${env.TEST_PHP_FPM_SERVICE_NAME}')..."
                        docker run -d \\
                            --name "${env.TEST_PHP_FPM_SERVICE_NAME}" \\
                            --network "${env.TEST_NETWORK_NAME}" \\
                            -e DB_HOSTNAME="${env.TEST_DB_HOSTNAME}" \\
                            -e DB_USERNAME="${env.TEST_DB_USERNAME}" \\
                            -e DB_PASSWORD="${env.TEST_DB_PASSWORD}" \\
                            -e DB_NAME="${env.TEST_DB_NAME}" \\
                            "${phpImageFullName}"
                        echo "INFO: TEST PHP-FPM container started."

                        echo "INFO: Starting TEST Apache container ('${env.TEST_APACHE_CONTAINER_NAME}')..."
                        docker run -d \\
                            --name "${env.TEST_APACHE_CONTAINER_NAME}" \\
                            --network "${env.TEST_NETWORK_NAME}" \\
                            -p "${env.TEST_APACHE_EXPOSED_PORT}:80" \\
                            "${apacheImageFullName}"
                        echo "INFO: TEST Apache container started."
                        
                        echo "SUCCESS: Test environment deployed!"
                        echo "Test application accessible at: http://localhost:${env.TEST_APACHE_EXPOSED_PORT}"
                        docker ps --filter network="${env.TEST_NETWORK_NAME}"
                    """
                    echo "-------------------------------------------------------------------"
                }
            }
        }

        stage('Automated Tests (Smoke Test on Test Env)') {
            environment {
                // Make sure TEST_APACHE_EXPOSED_PORT is accessible here, it is from global env...
                TARGET_APP_URL = "http://localhost:${env.TEST_APACHE_EXPOSED_PORT}"
            }
            steps {
                script {
                    echo "-------------------------------------------------------------------"
                    echo "INFO: Starting Stage: Automated Tests (Smoke Test on Test Env)"
                    sh """
                        set -e
                        EXPECTED_HTTP_CODE="200"
                        TEST_SUCCESS=true
                        # Use the TARGET_APP_URL from the stage's environment
                        INDEX_PAGE_URL="\${TARGET_APP_URL}/index.php" 

                        echo "INFO: Test 1: Checking accessibility of \${INDEX_PAGE_URL}"
                        HTTP_CODE=\$(curl -s -o /dev/null -w "%{http_code}" "\${INDEX_PAGE_URL}")

                        if [ "\${HTTP_CODE}" -eq "\${EXPECTED_HTTP_CODE}" ]; then
                            echo "SUCCESS: Index page returned HTTP \${HTTP_CODE}."
                        else
                            echo "ERROR: Index page returned HTTP \${HTTP_CODE}. Expected \${EXPECTED_HTTP_CODE}."
                            TEST_SUCCESS=false
                        fi

                        if \${TEST_SUCCESS}; then
                            echo "INFO: Test 2: Checking content of \${INDEX_PAGE_URL} for text: '${env.EXPECTED_TEXT}'"
                            if curl -s -L "\${INDEX_PAGE_URL}" | grep -q "${env.EXPECTED_TEXT}"; then
                                echo "SUCCESS: Expected text '${env.EXPECTED_TEXT}' found on the index page."
                            else
                                echo "ERROR: Expected text '${env.EXPECTED_TEXT}' NOT found on the index page."
                                TEST_SUCCESS=false
                            fi
                        fi

                        if \${TEST_SUCCESS}; then
                            echo "SUCCESS: All smoke tests passed for Test Environment!"
                        else
                            echo "ERROR: One or more smoke tests FAILED for Test Environment."
                            error "Smoke tests failed for Test Environment"
                        fi
                    """
                    echo "-------------------------------------------------------------------"
                }
            }
        }
        
        stage('Code Quality Analysis') {
            steps {
                script {
                    echo "-------------------------------------------------------------------"
                    echo "INFO: Starting Stage: Code Quality Analysis with SonarQube"
                    try {
                        sh """
                        docker run --rm \\
                            -e SONAR_HOST_URL="${env.SONAR_HOST_URL_ENV}" \\
                            -e SONAR_LOGIN="${env.SONAR_TOKEN_ENV}" \\
                            -v "${env.WORKSPACE}:/usr/src" \\
                            sonarsource/sonar-scanner-cli \\
                            -Dsonar.projectKey=${env.SONAR_PROJECT_KEY} \\
                            -Dsonar.sources=public \\
                            -Dsonar.host.url=${env.SONAR_HOST_URL_ENV} \\
                            -Dsonar.login=${env.SONAR_TOKEN_ENV}
                        """
                        echo "SUCCESS: SonarQube analysis submitted."
                    } catch (Exception e) {
                        echo "ERROR: SonarQube analysis FAILED: ${e.getMessage()}"
                        currentBuild.result = 'FAILURE'; error "SonarQube analysis failed"
                    }
                    echo "-------------------------------------------------------------------"
                }
            }
        }

        stage('Security Analysis & Approval') {
            steps {
                script {
                    echo "-------------------------------------------------------------------"
                    echo "INFO: Starting Stage: Security Analysis & Approval"
                    echo "INFO: SonarQube scan completed. Review dashboard: ${env.SONAR_HOST_URL_ENV}/dashboard?id=${env.SONAR_PROJECT_KEY}"

                    // Send email notification for pending approval
                    try {
                        emailext (
                            to: 'katalinali@gmail.com', // <<< *** REPLACE THIS with the actual approver's email ***
                            subject: "Jenkins [${env.JOB_NAME}] - Build #${env.BUILD_NUMBER}: Security Review Required",
                            body: """<p>Hello Approver,</p>
                                       <p>The SonarQube security scan for Jenkins job <b>${env.JOB_NAME}</b>, build <b>#${env.BUILD_NUMBER}</b> has completed and requires your review.</p>
                                       <p>A manual security review and approval is needed to allow the pipeline to proceed to the Release stage.</p>
                                       <hr>
                                       <p><b>SonarQube Report:</b> <a href="${env.SONAR_HOST_URL_ENV}/dashboard?id=${env.SONAR_PROJECT_KEY}">${env.SONAR_HOST_URL_ENV}/dashboard?id=${env.SONAR_PROJECT_KEY}</a></p>
                                       <p><b>Approve/Abort in Jenkins:</b> <a href="${env.BUILD_URL}input/">${env.BUILD_URL}input/</a></p>
                                       <hr>
                                       <p>Thank you.</p>""",
                            mimeType: 'text/html' // Allows HTML content in the email
                        )
                        echo "INFO: Approval notification email sent successfully."
                    } catch (Exception e) {
                        echo "WARN: Failed to send approval notification email. Please check Jenkins email configuration and plugin. Error: ${e.getMessage()}"
                        // Optionally, you could choose to not fail the pipeline here, allowing the input step to still proceed.
                        // If email is critical, you could re-throw the error or set currentBuild.result.
                    }

                    try {
                        timeout(time: 4, unit: 'HOURS') { 
                            input(
                                id: 'securityApproval', 
                                message: "Security Review Gate: Check SonarQube (${env.SONAR_HOST_URL_ENV}/dashboard?id=${env.SONAR_PROJECT_KEY}). Approve to continue to Release?",
                                parameters: [[$class: 'BooleanParameterDefinition', defaultValue: true, description: 'Approve release?', name: 'isApproved']]
                            )
                        }
                        echo "INFO: Security review approved. Proceeding."
                    } catch (org.jenkinsci.plugins.workflow.steps.FlowInterruptedException e) {
                        echo "ERROR: Security Review was aborted or timed out."
                        currentBuild.result = 'ABORTED'; error "Pipeline aborted at Security Review Gate."
                    }
                    echo "-------------------------------------------------------------------"
                }
            }
        }

        stage('Release to Docker Hub') {
            steps {
                script {
                    echo "-------------------------------------------------------------------"
                    echo "INFO: Starting Stage: Release to Docker Hub"
                    
                    def releaseTag = "v1.0.${env.APP_VERSION}" 

                    def localPhpImageFullName    = "${env.PHP_IMAGE_PREFIX}:${env.APP_VERSION}"
                    def localApacheImageFullName = "${env.APACHE_IMAGE_PREFIX}:${env.APP_VERSION}"
                    def localMysqlImageFullName  = "${env.MYSQL_IMAGE_PREFIX}:${env.APP_VERSION}"

                    def targetPhpImage    = "${env.DOCKERHUB_USER}/${env.PHP_APP_IMAGE_BASE_NAME}:${releaseTag}"
                    def targetApacheImage = "${env.DOCKERHUB_USER}/${env.APACHE_APP_IMAGE_BASE_NAME}:${releaseTag}"
                    def targetMysqlImage  = "${env.DOCKERHUB_USER}/${env.MYSQL_APP_IMAGE_BASE_NAME}:${releaseTag}"

                    echo "INFO: Release Tag: ${releaseTag}"
                    // ... (rest of echo statements)

                    withCredentials([usernamePassword(credentialsId: env.DOCKERHUB_CREDENTIALS_ID, usernameVariable: 'DOCKER_USER', passwordVariable: 'DOCKER_PASS')]) {
                        try {
                            echo "INFO: Logging in to Docker Hub..."
                            sh "docker login -u \"${DOCKER_USER}\" -p \"${DOCKER_PASS}\""

                            sh "docker tag \"${localPhpImageFullName}\" \"${targetPhpImage}\""
                            sh "docker push \"${targetPhpImage}\""
                            echo "SUCCESS: Pushed PHP image."

                            sh "docker tag \"${localApacheImageFullName}\" \"${targetApacheImage}\""
                            sh "docker push \"${targetApacheImage}\""
                            echo "SUCCESS: Pushed Apache image."
                            
                            sh "docker tag \"${localMysqlImageFullName}\" \"${targetMysqlImage}\""
                            sh "docker push \"${targetMysqlImage}\""
                            echo "SUCCESS: Pushed MySQL image."

                            // ... (Git tagging part - ensure Jenkins has push credentials or do it manually)
                            echo "INFO: Git commit tagging can be added here if Jenkins has Git push credentials."

                        } catch (Exception e) {
                            echo "ERROR: Release stage failed: ${e.getMessage()}"
                            currentBuild.result = 'FAILURE'; error "Release stage failed."
                        } finally {
                            echo "INFO: Logging out from Docker Hub..."
                            sh "docker logout"
                        }
                    }
                    echo "-------------------------------------------------------------------"
                }
            }
        }

// Jenkinsfile
// ... (previous stages and environment block) ...

        stage('Deploy to Production (Minikube)') {
            steps {
                script {
                    echo "-------------------------------------------------------------------"
                    echo "INFO: Starting Stage: Deploy to Production (Minikube)"
                    
                    def releaseTag = "v1.0.${env.APP_VERSION}"
                    def k8sManifestPath = "k8s" // Directory containing your YAML files
                    //// def kubeconfigCredentialsId = 'kubeconfig-credentials' // The ID you set in Jenkins Credentials

                    // Use withCredentials to securely access the kubeconfig file
                    //// withCredentials([file(credentialsId: kubeconfigCredentialsId, variable: 'KUBECONFIG_FILE_PATH')]) {
                        // KUBECONFIG_FILE_PATH is now a temporary path to your kubeconfig file.
                        // We'll set the KUBECONFIG environment variable for the shell commands.
                        //// env.KUBECONFIG = KUBECONFIG_FILE_PATH

                        //// echo "INFO: Using Kubeconfig: ${env.KUBECONFIG}"
                        //// echo "INFO: Applying Kubernetes manifests from '${k8sManifestPath}' directory..."

                        // The sed commands to update image tags in your YAML files should also be within this
                        // withCredentials block if you are checking out fresh each time or need to ensure
                        // kubectl uses the correct config.
                        // For example (ensure these sed commands are correct for your YAML structure):
                        def dockerHubUser = env.DOCKERHUB_USER
                        def mysqlImageBaseName = env.MYSQL_APP_IMAGE_BASE_NAME
                        def phpImageBaseName   = env.PHP_APP_IMAGE_BASE_NAME
                        def apacheImageBaseName= env.APACHE_APP_IMAGE_BASE_NAME

                        sh """
                            kubectl cluster-info
                            echo "INFO: Updating image tags in Kubernetes manifests to: ${releaseTag}"
                            sed -i 's|image: ${dockerHubUser}/${mysqlImageBaseName}:.*|image: ${dockerHubUser}/${mysqlImageBaseName}:${releaseTag}|g' ${k8sManifestPath}/mysql-prod.yaml
                            sed -i 's|image: ${dockerHubUser}/${phpImageBaseName}:.*|image: ${dockerHubUser}/${phpImageBaseName}:${releaseTag}|g' ${k8sManifestPath}/php-prod.yaml
                            sed -i 's|image: ${dockerHubUser}/${apacheImageBaseName}:.*|image: ${dockerHubUser}/${apacheImageBaseName}:${releaseTag}|g' ${k8sManifestPath}/apache-prod.yaml
                        """

                        echo "INFO: Applying Kubernetes manifests from ${k8sManifestPath} directory..."
                    
                        sh "kubectl create namespace ${env.PROD_K8S_NAMESPACE} || echo 'INFO: Namespace ${env.PROD_K8S_NAMESPACE} already exists or error.'"
                        
                        sh "kubectl apply -f ${k8sManifestPath}/mysql-prod.yaml --namespace=${env.PROD_K8S_NAMESPACE}"
                        sh "kubectl apply -f ${k8sManifestPath}/php-prod.yaml --namespace=${env.PROD_K8S_NAMESPACE}"
                        sh "kubectl apply -f ${k8sManifestPath}/apache-prod.yaml --namespace=${env.PROD_K8S_NAMESPACE}"

                        echo "INFO: Waiting for deployments to roll out..."
                        sh "kubectl rollout status deployment/s753-mysql-prod-deployment --namespace=${env.PROD_K8S_NAMESPACE} --timeout=180s"
                        sh "kubectl rollout status deployment/s753-php-prod-deployment --namespace=${env.PROD_K8S_NAMESPACE} --timeout=180s"
                        sh "kubectl rollout status deployment/s753-apache-prod-deployment --namespace=${env.PROD_K8S_NAMESPACE} --timeout=180s"
                        
                        // ... rest of the stage ...
                }
            }
        }

        stage('Monitoring & Alerting (Health Check on Prod)') {
            steps {
                script {
                    echo "-------------------------------------------------------------------"
                    echo "INFO: Starting Stage: Monitoring & Alerting (Health Check on Prod)"
                    
                    // These environment variables should be defined in your global environment block
                    // env.PROD_APACHE_K8S_SVC_NAME = "s753-apache-prod-svc"
                    // env.PROD_K8S_NAMESPACE = "s753-production"

                    def minikubeIp = ''
                    def nodePort = ''
                    def prodAppUrl = ''
                    def healthCheckUrl = ''
                    boolean healthy = false

                    try {
                        echo "INFO: Determining Production URL from Minikube..."
                        minikubeIp = sh(script: "minikube ip", returnStdout: true).trim()
                        nodePort = sh(script: "kubectl get svc ${env.PROD_APACHE_K8S_SVC_NAME} --namespace=${env.PROD_K8S_NAMESPACE} -o jsonpath='{.spec.ports[0].nodePort}'", returnStdout: true).trim()
                        
                        if (!minikubeIp || minikubeIp.isEmpty() || !nodePort || nodePort.isEmpty()) {
                            error "ERROR: Could not determine Minikube IP or NodePort for health check."
                        }

                        prodAppUrl = "http://${minikubeIp}:${nodePort}"
                        healthCheckUrl = "${prodAppUrl}/healthcheck.php"
                        echo "INFO: Production Health Check URL: ${healthCheckUrl}"

                        // Perform the health check
                        sh """
                            set -e  # Exit on error
                            echo "INFO: Checking PRODUCTION health at: ${healthCheckUrl}"
                            
                            # Attempt to curl the health check endpoint.
                            # Save HTTP status code and body separately.
                            HTTP_CODE=\$(curl -s -L -w "%{http_code}" "${healthCheckUrl}" -o health_check_response.json)
                            
                            echo "INFO: Health check HTTP status code: \${HTTP_CODE}"
                            echo "INFO: Health check response body:"
                            cat health_check_response.json || echo "WARN: health_check_response.json not found or empty."

                            if [ "\${HTTP_CODE}" -eq "200" ]; then
                                echo "SUCCESS: PRODUCTION Health check endpoint returned HTTP 200."
                                # Check if the JSON response body contains '"status": "OK"'
                                if grep -q '"status": "OK"' health_check_response.json; then
                                    echo "SUCCESS: PRODUCTION Application is healthy (found 'status: OK')."
                                    # If you want to mark healthy here, you could use a marker file or env var for groovy
                                else
                                    echo "ERROR: PRODUCTION Application is NOT healthy (content mismatch: 'status: OK' not found)."
                                    exit 1 # Fail the shell script, hence the stage
                                fi
                            else
                                echo "ERROR: PRODUCTION Health check endpoint returned HTTP \${HTTP_CODE}. Expected 200."
                                exit 1 # Fail the shell script, hence the stage
                            fi
                        """
                        // If sh script exits with 0, it means health check passed
                        healthy = true 
                        echo "SUCCESS: Production health check passed."

                    } catch (Exception e) {
                        // This catch block will catch failures from 'error' step or sh step if it fails Jenkins build
                        echo "ERROR: Monitoring stage failed during execution: ${e.getMessage()}"
                        // currentBuild.result is already set to FAILURE by 'error' or failed sh step
                    } finally {
                        sh "rm -f health_check_response.json || true" // Clean up temp file
                        if (!healthy) {
                             // Ensure build is marked as failure if any check logically failed but didn't exit script
                            if (currentBuild.result == null || currentBuild.result == 'SUCCESS') {
                                currentBuild.result = 'FAILURE'
                            }
                            // The 'error' call inside the sh block would have already stopped it.
                            // This is a fallback if the shell script logic for exit 1 had an issue.
                        }
                    }
                    
                    if (!healthy) {
                         error "ALERT: PRODUCTION Environment Health Check FAILED!" // This will ensure pipeline stops and fails
                    }
                    echo "-------------------------------------------------------------------"
                }
            }
        }

        stage('Deploy Monitoring System (Prometheus to Minikube)') {
            steps {
                script {
                    echo "-------------------------------------------------------------------"
                    echo "INFO: Starting Stage: Deploy Monitoring System (Prometheus to Minikube)"
                    echo "INFO: Ensure Helm v3 is installed on the Jenkins agent."

                    sh """
                        kubectl cluster-info
                        echo "INFO: Adding Prometheus Helm community repository if not already added..."
                        helm repo add prometheus-community https://prometheus-community.github.io/helm-charts || echo "INFO: Prometheus repo may already be added or error occurred (continuing)."
                        echo "INFO: Adding Prometheus Helm community repository if not already added..."
                        helm repo update

                        echo "INFO: Installing or Upgrading Prometheus release '${env.PROMETHEUS_RELEASE_NAME}' in namespace '${env.PROMETHEUS_NAMESPACE}'..."
                        # --create-namespace will create the namespace if it doesn't exist.
                        # --wait makes Helm wait until all resources are in a ready state.
                        # We set server.service.type to NodePort for easier access in Minikube.
                        # Test with webhook
                        helm upgrade --install "${env.PROMETHEUS_RELEASE_NAME}" prometheus-community/prometheus \\
                            --namespace "${env.PROMETHEUS_NAMESPACE}" \\
                            --create-namespace \\
                            --set server.service.type=NodePort \\
                            --set server.service.nodePort=${env.PROMETHEUS_NODE_PORT}


                        echo "INFO: Prometheus Helm chart deployment attempt complete."
                        echo "INFO: Prometheus server should be accessible via NodePort."
                        echo "INFO: To access Prometheus UI:"
                        echo "INFO: 1. Get your Minikube IP by running: minikube ip"
                        echo "INFO: 2. Open your browser to: http://<Minikube_IP>:${env.PROMETHEUS_NODE_PORT}"
                        echo "INFO: It might take a few moments for all Prometheus components to be fully ready and accessible."
                    """

                    echo "-------------------------------------------------------------------"
                }
            }
        }

    } // End of stages


}