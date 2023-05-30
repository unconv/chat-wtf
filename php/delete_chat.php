<?php
session_start();

if( ! isset( $_POST['chat_id'] ) ) {
    die( "ERROR: No chat_id provided" );
}

$chat_id = $_POST['chat_id'];

unset( $_SESSION['chats'][$chat_id] );

echo "DELETED";
