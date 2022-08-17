<?php
header("Access-Control-Allow-Origin: *");
$action = $_POST["action"];

    echo json_encode( array( 'url' => "bamboo.com", 'whatever' => "This is a test", 'id' => "2", 'name' => "max") );
