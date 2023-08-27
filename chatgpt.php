<?php
/**
 * Sends a message to ChatGPT API, streams and returns the response
 * 
 * @param array $messages Messages to be sent to ChatGPT
 * @param string $api_key OpenAI API-key
 * @param string $model OpenAI model
 * 
 * @return string Response from ChatGPT
 */

require_once( __DIR__ . "/exceptions.php" );

function send_chatgpt_message(
    array $messages,
    string $api_key,
    string $model = "gpt-3.5-turbo"
): string {
    $ch = curl_init( "https://api.openai.com/v1/chat/completions" );

    $response_text = "";

    $messages = array_map( function( $message ) {
        return [
            "role" => $message["role"],
            "content" => $message["content"],
        ];
    }, $messages );

    curl_setopt_array( $ch, [
        CURLOPT_HTTPHEADER => [
            "Content-type: application/json",
            "Authorization: Bearer $api_key"
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode( [
            "model" => $model,
            "messages" => $messages,
            "stream" => true,
            "temperature" => 0.9,
            "frequency_penalty" => 0.5,
        ] ),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_WRITEFUNCTION => function( $ch, $data ) use ( &$response_text ) {
            $json = json_decode( $data );

            if( isset( $json->error ) ) {
                $error  = $json->error->message;
                $error .= " (" . $json->error->code . ")";
                $error  = "`" . trim( $error ) . "`";

                echo "data: " . json_encode( ["content" => $error] ) . "\n\n";

                echo "event: stop\n";
                echo "data: stopped\n\n";

                flush();
                die();
            }

            $deltas = explode( "\n", $data );
        
            foreach( $deltas as $delta ) {
                if( strpos( $delta, "data: " ) !== 0 ) {
                    continue;
                }
        
                $json = json_decode( substr( $delta, 6 ) );
        
                if( isset( $json->choices[0]->delta ) ) {
                    $content = $json->choices[0]->delta->content ?? "";
                } elseif( trim( $delta ) == "data: [DONE]" ) {
                    $content = "";
                } else {
                    error_log( "Invalid ChatGPT response: '" . $delta . "'" );

                    file_put_contents("data.txt", $data);
                }
        
                $response_text .= $content;
        
                echo "data: " . json_encode( ["content" => $content] ) . "\n\n";
                flush();
            }
        
            if( connection_aborted() ) return 0;
        
            return strlen( $data );
        }
    ] );
    
    $response = curl_exec( $ch );

    if( ! $response ) {
        throw new CurlErrorException( sprintf(
            "Error in OpenAI request: %s",
            curl_errno( $ch ) . ": " . curl_error( $ch )
        ) );
    }

    if( ! $response_text ) {
        throw new OpenAIErrorException( sprintf(
            "Unknown in OpenAI API request"
        ) );
    }

    return $response_text;
}
