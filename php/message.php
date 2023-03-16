<?php
require_once(__DIR__."/vendor/autoload.php");

$settings = require( __DIR__ . "/settings.php" );

use Orhanerday\OpenAi\OpenAi;
use League\CommonMark\CommonMarkConverter;

header( "Content-Type: application/json" );

$context = json_decode( $_POST['context'] ?? "[]" ) ?: [];

// initialize OpenAI api
$openai = new OpenAi( $settings['api_key'] );

$messages = [];

if( ! empty( $settings['system_message'] ) ) {
    $messages[] = [
        "role" => "system",
        "content" => $settings['system_message'],
    ];
}

foreach( $context as $msg ) {
    $messages[] = [
        "role" => "user",
        "content" => $msg[0],
    ];
    $messages[] = [
        "role" => "assistant",
        "content" => $msg[1],
    ];
}

$messages[] = [
    "role" => "user",
    "content" => $_POST['message'],
];

// create a new completion
$complete = json_decode( $openai->chat( [
    'model' => 'gpt-3.5-turbo',
    'messages' => $messages,
    'temperature' => 1.0,
    'max_tokens' => 2000,
    'frequency_penalty' => 0,
    'presence_penalty' => 0,
 ] ) );

// get message text
if( isset( $complete->choices[0]->message->content ) ) {
    $text = str_replace( "\\n", "\n", $complete->choices[0]->message->content );
} elseif( isset( $complete->error->message ) ) {
    $text = $complete->error->message;
} else {
    $text = "Sorry, but I don't know how to answer that.";
}

// log for debugging
// error_log( $text );

// convert markdown to HTML
$converter = new CommonMarkConverter();
$styled = $converter->convert( $text );

// return response
echo json_encode( [
    "message" => (string)$styled,
    "raw_message" => $text,
    "status" => "success",
] );
