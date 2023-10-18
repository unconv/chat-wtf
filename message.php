<?php
header( "Cache-Control: no-cache" );
if( ob_get_level() ) ob_end_clean();

$settings = require( __DIR__ . "/settings.php" );

require( __DIR__ . "/database.php" );
require( __DIR__ . "/autoload.php" );

$mode = $_REQUEST['mode'] ?? "normal";

$code_interpreter_enabled = (
    isset( $settings['code_interpreter']['enabled'] ) &&
    $settings['code_interpreter']['enabled'] === true &&
    $mode === "code_interpreter"
);

$model = $_REQUEST['model'] ?? $settings['model'] ?? "gpt-3.5-turbo";

$db = get_db();
$conversation_class = get_conversation_class( $db );

// get chat history from session
$chat_id = intval( $_REQUEST['chat_id'] );

$conversation = $conversation_class->find( $chat_id, $db );

if( ! $conversation ) {
    $conversation = new $conversation_class( $db );
    // TODO: save model to conversation
    // TODO: save mode to conversation
    $conversation->set_title( "Untitled chat" );
    $conversation->save();
    $chat_id = $conversation->get_id();
}

$context = $conversation->get_messages();

if( empty( $context ) && ! empty( $settings['system_message'] ) ) {
    $system_message = [
        "role" => "system",
        "content" => $settings['system_message'],
    ];

    $context[] = $system_message;
    $conversation->add_message( $system_message );
}

if( isset( $_POST['message'] ) ) {
    $message = [
        "role" => "user",
        "content" => $_POST['message'],
    ];

    $conversation->add_message( $message );

    echo $conversation->get_id();
    exit;
}

header( "Content-type: text/event-stream" );

$error = null;

// create a new completion
try {
    $chatgpt = new ChatGPT( $settings['api_key'] );

    if( $code_interpreter_enabled ) {
        $code_interpreter = new CodeInterpreter( $chat_id );
        $chatgpt = $code_interpreter->init_chatgpt( $chatgpt );
    }

    if( isset( $settings['model'] ) ) {
        $chatgpt->set_model( $model );
    }

    foreach( $context as $message ) {
        switch( $message['role'] ) {
            case "user":
                $chatgpt->umessage( $message['content'] );
                break;
            case "assistant":
                $chatgpt->amessage( $message['content'] );
                break;
            case "system":
                $chatgpt->smessage( $message['content'] );
                break;
        }
    }

    if( $code_interpreter_enabled ) {
        $response_text = $chatgpt->response()->content;

        $code_interpreter->fake_stream( $response_text );
    } else {
        $response_text = $chatgpt->stream( StreamType::Event )->content;
    }

} catch ( Exception $e ) {
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

$conversation->add_message( $assistant_message );

echo "event: stop\n";
echo "data: stopped\n\n";
