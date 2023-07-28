<?php
require( __DIR__ . "/database.php" );
$db = get_db();

if( ! isset( $_POST['chat_id'] ) ) {
    die( "ERROR: No chat_id provided" );
}

$chat_id = intval( $_POST['chat_id'] );

delete_chat( $chat_id, $db );

echo "DELETED";
