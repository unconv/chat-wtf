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

$model = $_REQUEST['model'] ?? $settings['model'] ?? "undefined";

if( empty( $model ) ) {
    $model = $settings["model"] ?? "undefined";
}

$db = get_db();
$conversation_class = get_conversation_class( $db );

// get chat history from session
$chat_id = intval( $_REQUEST['chat_id'] );

$conversation = $conversation_class->find( $chat_id, $db );

if( ! $conversation ) {
    $conversation = new $conversation_class( $db );
    $conversation->set_title( "Untitled chat" );
    $conversation->set_mode( $mode );
    $conversation->set_model( $model );
    $conversation->save();
    $chat_id = $conversation->get_id();
}

if( $code_interpreter_enabled ) {
    $code_interpreter = new CodeInterpreter( $chat_id );
}

$context = $conversation->get_messages();

if( empty( $context ) && ! empty( $settings['system_message'] ) ) {
    $system_message = new Message(
        role: "system",
        content: $settings['system_message'],
    );

    $context[] = $system_message;
    $conversation->add_message( $system_message );
}

if( isset( $_POST['message'] ) ) {
    $last_message = $context[count( $context ) - 1] ?? null;

    $message = new Message(
        role: "user",
        content: $_POST['message'],
    );

    $conversation->add_message( $message );

    if(
        $code_interpreter_enabled &&
        $last_message &&
        $last_message->role === "function_call" &&
        $last_message->function_name === "python" &&
        $_POST['message'] === "Yes, run the code."
    ) {
        $code = CodeInterpreter::parse_arguments( $last_message->function_arguments );

        $response = $code_interpreter->python( $code );

        $message = new Message(
            role: "function",
            content: $response,
            function_name: "python",
        );

        $conversation->add_message( $message );
    }

    echo $conversation->get_id();
    exit;
}

header( "Content-type: text/event-stream" );

$error = null;

// create a new completion
try {
    $chatgpt = new ChatGPT( $settings['api_key'] );

    if( $code_interpreter_enabled ) {
        $chatgpt = $code_interpreter->init_chatgpt( $chatgpt );
    }

    if( isset( $settings['model'] ) ) {
        $chatgpt->set_model( $model );
    }

    if( isset( $settings['params'] ) ) {
        $chatgpt->set_params( $settings['params'] );
    }

    foreach( $context as $message ) {
        switch( $message->role ) {
            case "user":
                $chatgpt->umessage( $message->content );
                break;
            case "assistant":
                $chatgpt->amessage( $message->content );
                break;
            case "function_call":
                $chatgpt->fcall( $message->function_name, $message->function_arguments );
                break;
            case "function":
                $chatgpt->fresult( $message->function_name, $message->content );
                break;
            case "system":
                $chatgpt->smessage( $message->content );
                break;
        }
    }

    if( $code_interpreter_enabled ) {
        $response = $chatgpt->response( raw_function_response: true );

        if( isset( $response->function_call ) ) {
            $code = CodeInterpreter::parse_arguments( $response->function_call->arguments );

            echo "data: " . json_encode( [
                "role" => "function_call",
                "function_name" => $response->function_call->name,
                "function_arguments" => json_encode( ["code" => $code] ),
            ] ) . "\n\n";
            flush();

            $message = new Message(
                role: "function_call",
                function_name: $response->function_call->name,
                function_arguments: $response->function_call->arguments,
            );

            $conversation->add_message( $message );

            echo "event: stop\n";
            echo "data: stopped\n\n";
            exit;
        }

        $response_text = $response->content;

        $last_message = $context[count( $context ) - 1] ?? null;
        if( $last_message->role === "function" ) {
            $result_text = CodeInterpreter::parse_result( $last_message->content );
            $extra_response = "Result from code:\n```\n" . $result_text . "\n```\n\n";
        } else {
            $extra_response = "";
        }

        $code_interpreter->fake_stream( $extra_response . $response_text );
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

$assistant_message = new Message(
    role: "assistant",
    content: $response_text,
);

$conversation->add_message( $assistant_message );

echo "event: stop\n";
echo "data: stopped\n\n";
