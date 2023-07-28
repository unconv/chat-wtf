<?php
header( "Cache-Control: no-cache" );
ob_end_flush();

$settings = require( __DIR__ . "/settings.php" );

require( __DIR__ . "/database.php" );
require( __DIR__ . "/chatgpt.php" );

$db = get_db();

// get chat history from session
$chat_id = intval( $_REQUEST['chat_id'] );

if( ! chat_exists( $chat_id, $db ) ) {
    $chat_id = create_conversation( "Untitled chat", $db );
}

$context = get_messages( $chat_id, $db );

if( empty( $context ) && ! empty( $settings['system_message'] ) ) {
    $system_message = [
        "role" => "system",
        "content" => $settings['system_message'],
    ];

    $context[] = $system_message;
    add_message( $system_message, $chat_id, $db );
}

if( isset( $_POST['message'] ) ) {
    $message = [
        "role" => "user",
        "content" => $_POST['message'],
    ];

    add_message( $message, $chat_id, $db );

    echo $chat_id;
    exit;
}

header( "Content-type: text/event-stream" );

$error = null;

// create a new completion
try {
    $response_text = send_chatgpt_message(
        $context,
        $settings['api_key'],
        $settings['model'] ?? "",
    );
} catch( CurlErrorException $e ) {
    $error = "Sorry, there was an error in the connection. Check the error logs.";
} catch( OpenAIErrorException $e ) {
    $error = "Sorry, there was an unknown error in the OpenAI request";
}

if( $error !== null ) {
    $response_text = $error;
    echo "data: " . json_encode( ["content" => $error] ) . "\n\n";
    flush();
}

$assistant_message = [
    "role" => "assistant",
    "content" => $response_text,
];

add_message( $assistant_message, $chat_id, $db );

echo "event: stop\n";
echo "data: stopped\n\n";
