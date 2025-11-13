<?php
// alnaasDB.php

// Define database constants only if not already defined
if(!defined('DBHOST')) define('DBHOST', 'localhost');
if(!defined('DBUSER')) define('DBUSER', 'root');
if(!defined('DBPASS')) define('DBPASS', 'loro');
if(!defined('DBNAME')) define('DBNAME', 'alnaasDB');

// Create a MySQLi connection only if $conn is not already set
if(!isset($conn)) {
    $conn = new mysqli(DBHOST, DBUSER, DBPASS, DBNAME);

    // Check connection
    if($conn->connect_error){
        die("Database connection failed: " . $conn->connect_error);
    }
}
?>
