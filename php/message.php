<?php
session_start();
header( "Content-type: text/event-stream" );
header( "Cache-Control: no-cache" );
ob_end_flush();

$settings = require( __DIR__ . "/settings.php" );
require( __DIR__ . "/chatgpt.php" );

// get chat history from session
$context = $_SESSION['context'] ?? [];

$messages = [];

if( ! empty( $settings['system_message'] ) ) {
    $messages[] = [
        "role" => "system",
        "content" => $settings['system_message'],
    ];
}

foreach( $context as $msg ) {
    $messages[] = [
        "role" => $msg["role"],
        "content" => $msg["content"],
    ];
}

$messages[] = [
    "role" => "user",
    "content" => $_GET['message'],
];

$error = null;

// create a new completion
try {
    $response_text = send_chatgpt_message(
        $messages,
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

$messages[] = [
    "role" => "assistant",
    "content" => $response_text,
];

$_SESSION['context'] = $messages;

echo "event: stop\n";
echo "data: stopped\n\n";
