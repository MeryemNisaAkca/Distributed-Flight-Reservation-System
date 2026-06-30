<?php

$serverName = "NISA\SQLEXPRESS"; //Server Name in your computer
// Configures specific settings for the connection session.
$connectionOptions = array(
    "Database" => "THY_Project", 
    "CharacterSet" => "UTF-8",
    "TrustServerCertificate" => True
);

// Attempts to open a connection to the Microsoft SQL Server using the PHP SQLSRV driver.
$conn = sqlsrv_connect($serverName, $connectionOptions);
// Checks if the connection attempt returned false (failed).
if( $conn === false ) {
    $errors = sqlsrv_errors();
    error_log("Database connection failed: " . print_r($errors, true));
    die("Database connection error. Please contact administrator.");
}

?>