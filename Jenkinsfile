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
                // Make sure TEST_APACHE_EXPOSED_PORT is accessible here, it is from global env.
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
                    try {
                        timeout(time: 1, unit: 'HOURS') { 
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
                    def kubeconfigCredentialsId = 'kubeconfig-credentials' // The ID you set in Jenkins Credentials

                    // Use withCredentials to securely access the kubeconfig file
                    withCredentials([file(credentialsId: kubeconfigCredentialsId, variable: 'KUBECONFIG_FILE_PATH')]) {
                        // KUBECONFIG_FILE_PATH is now a temporary path to your kubeconfig file.
                        // We'll set the KUBECONFIG environment variable for the shell commands.
                        env.KUBECONFIG = KUBECONFIG_FILE_PATH

                        echo "INFO: Using Kubeconfig: ${env.KUBECONFIG}"
                        echo "INFO: Applying Kubernetes manifests from '${k8sManifestPath}' directory..."

                        // The sed commands to update image tags in your YAML files should also be within this
                        // withCredentials block if you are checking out fresh each time or need to ensure
                        // kubectl uses the correct config.
                        // For example (ensure these sed commands are correct for your YAML structure):
                        def dockerHubUser = env.DOCKERHUB_USER
                        def mysqlImageBaseName = env.MYSQL_APP_IMAGE_BASE_NAME
                        def phpImageBaseName   = env.PHP_APP_IMAGE_BASE_NAME
                        def apacheImageBaseName= env.APACHE_APP_IMAGE_BASE_NAME

                        sh """
                            echo "INFO: Updating image tags in Kubernetes manifests to: ${releaseTag}"
                            sed -i 's|image: ${dockerHubUser}/${mysqlImageBaseName}:.*|image: ${dockerHubUser}/${mysqlImageBaseName}:${releaseTag}|g' ${k8sManifestPath}/mysql-prod.yaml
                            sed -i 's|image: ${dockerHubUser}/${phpImageBaseName}:.*|image: ${dockerHubUser}/${phpImageBaseName}:${releaseTag}|g' ${k8sManifestPath}/php-prod.yaml
                            sed -i 's|image: ${dockerHubUser}/${apacheImageBaseName}:.*|image: ${dockerHubUser}/${apacheImageBaseName}:${releaseTag}|g' ${k8sManifestPath}/apache-prod.yaml
                        """

                        sh label: 'Deploy to Minikube K8s', script: """
                            set -e 
                            # The KUBECONFIG environment variable set by Groovy (env.KUBECONFIG)
                            # will be available to this shell script.

                            echo "INFO: Verifying kubectl connection to cluster using KUBECONFIG='${KUBECONFIG}'..."
                            kubectl cluster-info
                            kubectl version --short || echo "WARN: kubectl version --short failed, but continuing..."

                            echo "INFO: Creating namespace '${env.PROD_K8S_NAMESPACE}' if it doesn't exist..."
                            kubectl create namespace "${env.PROD_K8S_NAMESPACE}" || echo "INFO: Namespace '${env.PROD_K8S_NAMESPACE}' already exists or error creating."
                            
                            echo "INFO: Applying MySQL manifest..."
                            kubectl apply -f "${k8sManifestPath}/mysql-prod.yaml" --namespace="${env.PROD_K8S_NAMESPACE}"
                            echo "INFO: Applying PHP manifest..."
                            kubectl apply -f "${k8sManifestPath}/php-prod.yaml" --namespace="${env.PROD_K8S_NAMESPACE}"
                            echo "INFO: Applying Apache manifest..."
                            kubectl apply -f "${k8sManifestPath}/apache-prod.yaml" --namespace="${env.PROD_K8S_NAMESPACE}"

                            echo "INFO: Waiting for deployments to roll out..."
                            kubectl rollout status deployment/s753-mysql-prod-deployment --namespace="${env.PROD_K8S_NAMESPACE}" --timeout=180s
                            kubectl rollout status deployment/s753-php-prod-deployment --namespace="${env.PROD_K8S_NAMESPACE}" --timeout=180s
                            kubectl rollout status deployment/s753-apache-prod-deployment --namespace="${env.PROD_K8S_NAMESPACE}" --timeout=180s
                            
                            echo "SUCCESS: Production environment deployed to Minikube namespace '${env.PROD_K8S_NAMESPACE}'."
                            # ...
                        """
                    } // End of withCredentials
                    echo "-------------------------------------------------------------------"
                }
            }
        }
// ... (rest of your Jenkinsfile)


    } // End of stages


}