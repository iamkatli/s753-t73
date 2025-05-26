<?php
header('Content-Type: application/json');

$response = [
    'status' => 'ERROR', // Default to ERROR
    'timestamp' => date('c'),
    'checks' => []
];
$overall_ok = true;

// Check PHP (trivial check)
$response['checks']['php_service'] = ['status' => 'OK', 'version' => phpversion()];

// Check Database Connection
$servername = getenv('DB_HOSTNAME');
$username   = getenv('DB_USERNAME');
$password   = getenv('DB_PASSWORD');
$dbname     = getenv('DB_NAME');

if (!$servername || !$username || !$dbname) {
    $response['checks']['database_config'] = ['status' => 'ERROR', 'message' => 'DB environment variables not fully set.'];
    $overall_ok = false;
    http_response_code(500); // Internal Server Error due to config
} else {
    $conn = @new mysqli($servername, $username, $password, $dbname); // Suppress direct error output

    if ($conn->connect_error) {
        $response['checks']['database_connection'] = [
            'status' => 'ERROR',
            // 'message' => 'Could not connect: ' . $conn->connect_error // Avoid leaking too much info
            'message' => 'Database connection failed.'
        ];
        $overall_ok = false;
        if (http_response_code() === 200) http_response_code(503); // Service Unavailable
    } else {
        $response['checks']['database_connection'] = ['status' => 'OK', 'message' => 'Database connection successful.'];
        $conn->close();
    }
}

if ($overall_ok) {
    $response['status'] = 'OK';
} else {
    if (http_response_code() === 200) http_response_code(503); // Service Unavailable if not already set
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>