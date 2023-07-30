<?php
require( __DIR__ . "/database.php" );
require( __DIR__ . "/autoload.php" );

$db = get_db();
$conversation_class = get_conversation_class( $db );

if( ! isset( $_POST['chat_id'] ) ) {
    die( "ERROR: No chat_id provided" );
}

$chat_id = intval( $_POST['chat_id'] );

$conversation = $conversation_class->find( $chat_id, $db );

if( $conversation ) {
    $conversation->delete();

    echo "DELETED";
} else {
    echo "ERROR";
}
