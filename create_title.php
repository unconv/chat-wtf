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

function chatgpt_create_title(
    string $question,
    string $answer,
    string $api_key,
): string {
    $chatgpt = new ChatGPT( $api_key );
    $chatgpt->smessage( "Create a short title to be used in a conversation list in a chatbot. It should describe what the conversation is about (not a 'book title')" );
    $chatgpt->umessage( "Create a concise title for the following chat conversation:\nQ: " . $question . "\nA: " . $answer );

    return trim( $chatgpt->response()->content, '"' );
}

session_start();

$settings = require( __DIR__ . "/settings.php" );

require( __DIR__ . "/database.php" );
require( __DIR__ . "/autoload.php" );

$db = get_db();
$conversation_class = get_conversation_class( $db );

$title = chatgpt_create_title(
    $_POST['question'],
    $_POST['answer'],
    $settings['api_key']
);

$chat_id = intval( $_POST['chat_id'] );

$conversation = $conversation_class->find( $chat_id, $db );

if( $conversation ) {
    $conversation->set_title( $title );
    $conversation->save();

    echo $title;
} else {
    throw new \Exception( "Unable to create title" );
}
