<?php
header( "Content-type: text/event-stream" );
header( "Cache-Control: no-cache" );
ob_end_flush();

require_once(__DIR__."/vendor/autoload.php");

$settings = require( __DIR__ . "/settings.php" );

use Orhanerday\OpenAi\OpenAi;

// @todo: save context in a session
$context = json_decode( $_GET['context'] ?? "[]" ) ?: [];

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
    "content" => $_GET['message'],
];

// create a new completion
$complete = json_decode( $openai->chat( [
    'model' => 'gpt-3.5-turbo',
    'messages' => $messages,
    'temperature' => 1.0,
    'max_tokens' => 2000,
    'frequency_penalty' => 0,
    'presence_penalty' => 0,
    'stream' => true,
], function( $ch, $data ) {
    $deltas = explode( "\n", $data );

    foreach( $deltas as $delta ) {
        if( strpos( $delta, "data: " ) !== 0 ) {
            continue;
        }

        $json = json_decode( substr( $delta, 6 ) );

        if( isset( $json->choices[0]->delta ) ) {
            $content = $json->choices[0]->delta->content ?? "";
        } elseif( isset( $json->error->message ) ) {
            $content = $json->error->message;
        } elseif( trim( $delta ) == "data: [DONE]" ) {
            $content = "";
        } else {
            $content = "Sorry, but I don't know how to answer that.";
        }

        echo "data: " . str_replace( "\n", "\\n", $content ) . "\n\n";
        flush();
    }

    if( connection_aborted() ) return 0;

    return strlen( $data );
} ) );

echo "event: stop\n";
echo "data: stopped\n\n";
