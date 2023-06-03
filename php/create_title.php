<?php
/**
 * Creates a title for a chat conversation based on the
 * first question and answer
 * 
 * @param string $question Question the user asked
 * @param string $answer Answer by ChatGPT
 * @param string $api_key OpenAI API-key
 * @param string $model OpenAI model
 * 
 * @return string Title from ChatGPT
 */

require_once( __DIR__ . "/exceptions.php" );

function chatgpt_create_title(
    string $answer,
    string $question,
    string $api_key,
    string $model = "gpt-3.5-turbo"
): string {
    $ch = curl_init( "https://api.openai.com/v1/chat/completions" );

    curl_setopt_array( $ch, [
        CURLOPT_HTTPHEADER => [
            "Content-type: application/json",
            "Authorization: Bearer $api_key"
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode( [
            "model" => $model,
            "messages" => [
                [
                    "role" => "system",
                    "content" => "You are a title generator. Respond only with the title."
                ],
                [
                    "role" => "user",
                    "content" => "Create a concise title for the following chat conversation:\nQ: " . $question . "\nA: " . $answer
                ]
            ],
        ] ),
        CURLOPT_RETURNTRANSFER => true,
    ] );
    
    $response = curl_exec( $ch );

    if( ! $response ) {
        throw new CurlErrorException( sprintf(
            "Error in OpenAI request: %s",
            curl_errno( $ch ) . ": " . curl_error( $ch )
        ) );
    }

    $json = json_decode( $response );

    if( ! $json ) {
        throw new OpenAIErrorException( sprintf(
            "JSON error in OpenAI API request"
        ) );
    }

    if( ! isset( $json->choices[0]->message->content ) ) {
        throw new OpenAIErrorException( sprintf(
            "Error in OpenAI API request: %s",
            print_r( $json, true ),
        ) );
    }

    return trim( $json->choices[0]->message->content, '"' );
}

session_start();

$settings = require( __DIR__ . "/settings.php" );
$title = chatgpt_create_title(
    $_POST['question'],
    $_POST['answer'],
    $settings['api_key'],
    $settings['model'] ?? "",
);

$chat_id = $_POST['chat_id'];
$_SESSION['chats'][$chat_id]['title'] = $title;

echo $title;
