<?php
require( __DIR__ . "/../autoload.php" );

if( ! isset( $_GET['chat_id'] ) ) {
    http_response_code( 400 );
    die( "You didn't provide a chat_id" );
}

$chat_id = Uuid::sanitize( $_GET['chat_id'] );

if( ! isset( $_GET['file'] ) ) {
    http_response_code( 400 );
    die( "You didn't provide a file" );
}

$file = $_GET['file'];

$chat_dir = realpath( __DIR__ . "/../data/" . $chat_id );

if( ! $chat_dir ) {
    http_response_code( 404 );
    die( "File not found 1" );
}

$filepath = realpath( $chat_dir . "/" . $file );

if( ! $filepath ) {
    http_response_code( 404 );
    die( "File not found 2" );
}

if( strpos( $filepath, $chat_dir . "/" ) !== 0 ) {
    http_response_code( 404 );
    die( "File not found 3" );
}

header( "Content-type: " . mime_content_type( $filepath ) );

readfile( $filepath );
