<?php
$db = __DIR__."/chatwtf.db";

if( file_exists( $db ) ) {
    die( "ERROR: Database already exists.\n" );
}

$db = new PDO( "sqlite:" . $db );

$db->exec( file_get_contents( __DIR__ . "/sqlite.sql" ) );
