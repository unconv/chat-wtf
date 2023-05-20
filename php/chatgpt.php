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
function send_chatgpt_message(
    array $messages,
    string $api_key,
    string $model = "gpt-3.5-turbo"
): string {
    $ch = curl_init( "https://api.openai.com/v1/chat/completions" );

    $response_text = "";
    
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
        ] ),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_WRITEFUNCTION => function( $ch, $data ) use ( &$response_text ) {
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
        throw new \Exception( sprintf(
            "Error in OpenAI request: %s",
            curl_errno( $ch ) . ": " . curl_error( $ch )
        ) );
    }

    return $response_text;
}
