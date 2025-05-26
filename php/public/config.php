<?php
/*define('DB_SERVER', 'mysql');
define('DB_USERNAME', 'admin');
define('DB_PASSWORD', 'password');
define('DB_NAME', 'mydb');
 
// Attempt to connect to mysql database 
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
 
// Check connection
if($conn === false){
    die("ERROR: Could not connect to database. " . mysqli_connect_error());
}*/
$servername = "s753-test-mysql";
$username = "admin";
$password = "password";
$dbname = "mydb";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
