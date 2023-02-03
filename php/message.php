<?php
require_once(__DIR__."/vendor/autoload.php");

use Orhanerday\OpenAi\OpenAi;
use League\CommonMark\CommonMarkConverter;

header( "Content-Type: application/json" );

$context = json_decode( $_POST['context'] ?? "[]" ) ?: [];

// initialize OpenAI api
$openai = new OpenAi( trim( rtrim( file_get_contents( "api_key.txt" ) ) ) );

// set default prompt
$prompt = "Act as an AI mentor for a programmer by answering the questions provided. If the question is related to a piece of code, write the code and explain what it does and how it works in simple terms. Format the response in Markdown format so that the code can be distinguised from it easily. Please also explain the steps involved, don't only tell the code to use. Every response must have more than just code: at least one sentence about the code. If you're asked for your identity, say that your name is the magnificent ChatWTF.\n\n";

// add context to prompt
if( empty( $context ) ) {
    // if there is no context, use a default example question and answer
    $prompt .= "Question:\n'How do you write a hello world script in PHP?'\n\nAnswer:\nIn PHP, you can write a hello world script with the following code:\n\n```\n<?php\necho 'Hello world';\n?>\n```\n\nYou need to put this code into a file with the .php extension and then run it with PHP or with a web server.\n\nQuestion:\n'Can you use the print function instead?'\n\nAnswer:\nCertainly! Here's how you would use the `print` function insted:\n\n```\n<?php\nprint('Hello world');\n?>\n```\n\n";
    $please_use_above = "";
} else {
    // add old questions and answers to prompt
    $prompt .= "";
    $context = array_slice( $context, -5 );
    foreach( $context as $message ) {
        $prompt .= "Question:\n" . $message[0] . "\n\nAnswer:\n" . $message[1] . "\n\n";
    }
    $please_use_above = ". Please use the questions and answers above as context for the answer.";
}

// add new question to prompt
$prompt = $prompt . "Question:\n" . $_POST['message'] . $please_use_above . "\n\nAnswer:\n\n";

// create a new completion
$complete = json_decode( $openai->completion( [
    'model' => 'text-davinci-003',
    'prompt' => $prompt,
    'temperature' => 0.9,
    'max_tokens' => 2000,
    'top_p' => 1,
    'frequency_penalty' => 0,
    'presence_penalty' => 0,
    'stop' => [
        "\nNote:",
        "\nQuestion:"
    ]
] ) );

// get message text
if( isset( $complete->choices[0]->text ) ) {
    $text = str_replace( "\\n", "\n", $complete->choices[0]->text );
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
