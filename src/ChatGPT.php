<?php
/**
 * From: https://github.com/unconv/php-gpt-funcs/blob/master/library/ChatGPT.php
 */

enum StreamType: int {
    case Event = 0; // for JavaScript EventSource
    case Plain = 1; // for terminal application
    case Raw   = 2; // for raw data from ChatGPT API
}

class ChatGPT {
    /** @var array<stdClass> $messages */
    protected array $messages = [];
    protected array $functions = [];
    protected $savefunction = null;
    protected $loadfunction = null;
    protected bool $loaded = false;
    protected $tool_choice = "auto";
    protected string $model = "gpt-3.5-turbo";
    protected array $params = [];
    protected bool $assistant_mode = false;
    protected ?Assistant $assistant = null;
    protected ?string $thread_id = null;
    protected ?Run $run = null;

    public function __construct(
        protected string $api_key,
        protected ?string $chat_id = null
    ) {
        if( $this->chat_id === null ) {
            $this->chat_id = uniqid( more_entropy: true );
        }
    }

    public function load() {
        if( is_callable( $this->loadfunction ) ) {
            $this->messages = ($this->loadfunction)( $this->chat_id );
            $this->loaded = true;
        }
    }

    public function assistant_mode( bool $enabled ) {
        $this->assistant_mode = $enabled;
    }

    public function set_assistant( Assistant|string $assistant ) {
        if( is_string( $assistant ) ) {
            $this->assistant = $this->fetch_assistant( $assistant );
        } else {
            $this->assistant = $assistant;
        }
    }

    public function set_thread( Thread|string $thread ) {
        if( is_string( $thread ) ) {
            $this->thread_id = $thread;
        } else {
            $this->thread_id = $thread->get_id();
        }
    }

    public function set_model( string $model ) {
        $this->model = $model;
    }

    public function get_model() {
        return $this->model;
    }

    public function set_param( string $param, $value ) {
        $this->params[$param] = $value;
    }

    public function set_params( array $params ) {
        $this->params = $params;
    }

    public function get_params() {
        return $this->params;
    }

    public function version() {
        preg_match( "/gpt-(([0-9]+)\.?([0-9]+)?)/", $this->model, $matches );
        return floatval( $matches[1] );
    }

    public function force_tool_choice( array|string $tool_choice ) {
        $this->tool_choice = $tool_choice;
    }

    public function smessage( string $system_message ) {
        $message = [
            "role" => "system",
            "content" => $system_message,
        ];

        $this->messages[] = $message;

        if( is_callable( $this->savefunction ) ) {
            ($this->savefunction)( (object) $message, $this->chat_id );
        }
    }

    public function umessage( string $user_message ) {
        $message = new stdClass;
        $message->role = "user";
        $message->content = $user_message;

        $this->messages[] = $message;

        if( $this->assistant_mode ) {
            $this->add_assistants_message( $message );
        }

        if( is_callable( $this->savefunction ) ) {
            ($this->savefunction)( $message, $this->chat_id );
        }
    }

    /**
     * Add an assistant message to the context
     *
     * @param ?string $assistant_message The text content of the message or null for tool calls
     * @param array<stdClass> $tool_calls An array of tool calls in the OpenAI API format
     *
     * @return void
     */
    public function amessage(
        ?string $assistant_message = null,
        ?array $tool_calls = null
    ): void {
        $message = new stdClass;
        $message->role = "assistant";
        $message->content = $assistant_message;

        if( $tool_calls ) {
            $message->tool_calls = $tool_calls;
        }

        $this->messages[] = $message;

        if( is_callable( $this->savefunction ) ) {
            ($this->savefunction)( $message, $this->chat_id );
        }
    }

    public function fresult(
        string $tool_call_id,
        string $function_return_value
    ) {
        $message = new stdClass;
        $message->role = "tool";
        $message->content = $function_return_value;
        $message->tool_call_id = $tool_call_id;

        $this->messages[] = $message;

        if( is_callable( $this->savefunction ) ) {
            ($this->savefunction)( $message, $this->chat_id );
        }
    }

    public function assistant_response(
        bool $raw_function_response = false,
        ?StreamType $stream_type = null,
    ) {
        if( $this->run?->get_status() !== "requires_action" ) {
            $this->run = $this->create_run(
                thread_id: $this->thread_id,
                assistant_id: $this->assistant->get_id(),
            );
        }

        while( true ) {
            usleep( 1000*100 );

            $this->run = $this->fetch_run(
                thread_id: $this->thread_id,
                run_id: $this->run->get_id()
            );

            if( ! $this->run->is_pending() ) {
                break;
            }
        }

        if( $this->run->get_status() === "requires_action" ) {
            $required_action = $this->run->get_required_action();

            if( $required_action->type !== "submit_tool_outputs" ) {
                throw new \Exception( "Unrecognized required action type '".$required_action->type."'" );
            }

            $message = new stdClass;
            $message->role = "assistant";
            $message->content = null;
            $message->tool_calls = $required_action->submit_tool_outputs->tool_calls;
        } else {
            $messages = $this->get_thread_messages(
                thread_id: $this->thread_id,
                limit: 1,
                order: "desc",
            );

            $message = new stdClass;
            $message->role = $messages[0]->role;
            $message->content = $messages[0]->content[0]->text->value;
        }

        $message = $this->handle_functions( $message, $raw_function_response, stream_type: $stream_type );

        return $message;
    }

    public function response(
        bool $raw_function_response = false,
        ?StreamType $stream_type = null,
    ) {
        if( $this->assistant_mode ) {
            return $this->assistant_response(
                $raw_function_response,
                $stream_type, // TODO: streaming is not supported yet
            );
        }

        $params = [
            "model" => $this->model,
            "messages" => $this->messages,
        ];

        $params = array_merge( $params, $this->params );

        $functions = $this->get_functions();

        if( ! empty( $functions ) ) {
            $params["tools"] = $functions;
            $params["tool_choice"] = $this->tool_choice;
        }

        // make ChatGPT API request
        $ch = curl_init( "https://api.openai.com/v1/chat/completions" );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer " . $this->api_key
        ] );

        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

        if( $stream_type ) {
            $params["stream"] = true;

            $response_text = "";
            $partial_data = "";
            $functions = [];

            curl_setopt( $ch, CURLOPT_WRITEFUNCTION, function( $ch, $data ) use ( &$response_text, &$partial_data, &$functions, $stream_type ) {
                $response_text .= $this->parse_stream_data( $ch, $data, $stream_type, $partial_data, $functions );

                if( connection_aborted() ) {
                    return 0;
                }

                return strlen( $data );
            } );
        }

        curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode(
            $params
        ) );

        $curl_exec = curl_exec( $ch );

        // get ChatGPT reponse
        if( $stream_type ) {
            $message = new stdClass;
            $message->role = "assistant";
            $message->content = $response_text;

            if( count( $functions ) ) {
                $message->tool_calls = $functions;
            } else {
                if( $stream_type === StreamType::Event ) {
                    echo "event: stop\n";
                    echo "data: stopped\n\n";
                }
            }
        } else {
            $response = json_decode( $curl_exec );

            // somewhat handle errors
            if( ! isset( $response->choices[0]->message ) ) {
                if( isset( $response->error ) ) {
                    $error = trim( $response->error->message . " (" . $response->error->type . ")" );
                } else {
                    $error = $curl_exec;
                }
                throw new \Exception( "Error in OpenAI request: " . $error );
            }

            // add response to messages
            $message = $response->choices[0]->message;
        }

        $this->messages[] = $message;

        if( is_callable( $this->savefunction ) ) {
            ($this->savefunction)( $message, $this->chat_id );
        }

        $message = end( $this->messages );

        $message = $this->handle_functions( $message, $raw_function_response, stream_type: $stream_type );

        return $message;
    }

    public function stream( StreamType $stream_type ) {
        while( ob_get_level() ) ob_end_flush();
        return $this->response( stream_type: $stream_type );
    }

    protected function parse_stream_data( CurlHandle $ch, string $data, StreamType $stream_type, string &$partial_data, array &$functions ): string {
        $json = json_decode( $data );

        if( isset( $json->error ) ) {
            $error  = $json->error->message;
            $error .= " (" . $json->error->code . ")";
            $error  = "`" . trim( $error ) . "`";

            if( $stream_type == StreamType::Event ) {
                echo "data: " . json_encode( ["content" => $error] ) . "\n\n";

                echo "event: stop\n";
                echo "data: stopped\n\n";
            } elseif( $stream_type == StreamType::Plain ) {
                echo $error;
            } else {
                echo $data;
            }

            flush();
            die();
        }

        $response_text = "";

        $deltas = explode( "\n\n", $data );

        foreach( $deltas as $delta ) {
            $partial_data .= $delta;

            try {
                $json = json_decode( substr( $partial_data, 6 ), flags: JSON_THROW_ON_ERROR );
                $partial_data = "";
            } catch( JsonException $e ) {
                continue;
            }

            $content = "";

            if( ! isset( $json->choices[0]->delta ) ) {
                error_log( "Invalid ChatGPT response: '" . $partial_data . "'" );
                continue;
            }

            if( isset( $json->choices[0]->delta->tool_calls ) ) {
                foreach( $json->choices[0]->delta->tool_calls as $tool_call ) {
                    if( ! isset( $functions[$tool_call->index] ) ) {
                        $functions[$tool_call->index] = new stdClass;
                        $functions[$tool_call->index]->index = $tool_call->index;
                    }

                    if( isset( $tool_call->id ) ) {
                        if( ! isset( $functions[$tool_call->index]->id ) ) {
                            $functions[$tool_call->index]->id = "";
                        }

                        $functions[$tool_call->index]->id .= $tool_call->id;
                    }

                    if( isset( $tool_call->type ) ) {
                        if( ! isset( $functions[$tool_call->index]->type ) ) {
                            $functions[$tool_call->index]->type = "";
                        }

                        $functions[$tool_call->index]->type .= $tool_call->type;
                    }

                    if( ! isset( $functions[$tool_call->index]->function ) ) {
                        $functions[$tool_call->index]->function = new stdClass;
                    }

                    if( isset( $tool_call->function->name ) ) {
                        if( ! isset( $functions[$tool_call->index]->function->name ) ) {
                            $functions[$tool_call->index]->function->name = "";
                        }

                        $functions[$tool_call->index]->function->name .= $tool_call->function->name;
                    }

                    if( isset( $tool_call->function->arguments ) ) {
                        if( ! isset( $functions[$tool_call->index]->function->arguments ) ) {
                            $functions[$tool_call->index]->function->arguments = "";
                        }

                        $functions[$tool_call->index]->function->arguments .= $tool_call->function->arguments;
                    }
                }
            }

            if( isset( $json->choices[0]->delta->content ) ) {
                $content = $json->choices[0]->delta->content;
            }

            $response_text .= $content;

            if( $stream_type == StreamType::Event ) {
                echo "data: " . json_encode( ["content" => $content] ) . "\n\n";
            } elseif( $stream_type == StreamType::Plain ) {
                echo $content;
            } else {
                echo $data;
            }

            flush();
        }

        return $response_text;
    }

    protected function handle_functions( stdClass $message, bool $raw_function_response = false, ?StreamType $stream_type = null ) {
        if( isset( $message->tool_calls ) ) {
            $function_calls = array_filter(
                $message->tool_calls,
                fn( $tool_call ) => $tool_call->type === "function"
            );

            if( $raw_function_response ) {
                // for backwards compatibility
                if( count( $function_calls ) === 1 ) {
                    $message->function_call = $function_calls[0]->function;
                }

                return $message;
            }

            $tool_outputs = [];

            foreach( $function_calls as $tool_call ) {
                // get function name and arguments
                $function_call = $tool_call->function;
                $function_name = $function_call->name;
                $arguments = json_decode( $function_call->arguments, true );

                // sometimes ChatGPT responds with only a string of the
                // first argument instead of a JSON object
                if( $arguments === null ) {
                    $arguments = [$function_call->arguments];
                }

                $callable = $this->get_function( $function_name );

                if( is_callable( $callable ) ) {
                    $result = $callable( ...array_values( $arguments ) );
                } else {
                    $result = "Function '$function_name' unavailable.";
                }

                $tool_outputs[$tool_call->id] = $result;

                $this->fresult( $tool_call->id, $result );
            }

            if( $this->assistant_mode ) {
                $this->submit_tool_outputs(
                    $this->thread_id,
                    $this->run->get_id(),
                    $tool_outputs,
                );
            }

            return $this->response( stream_type: $stream_type );
        }

        return $message;
    }

    protected function get_function( string $function_name ): callable|false {
        if( $this->assistant_mode ) {
            $functions = $this->assistant->get_functions();
        } else {
            $functions = $this->functions;
        }

        foreach( $functions as $function ) {
            if( $function["name"] === $function_name ) {
                return $function["function"] ?? $function["name"];
            }
        }

        return false;
    }

    protected function get_functions( ?array $function_list = null ) {
        $tools = [];

        if( $function_list === null ) {
            $function_list = $this->functions;
        }

        foreach( $function_list as $function ) {
            $properties = [];
            $required = [];

            foreach( $function["parameters"] as $parameter ) {
                $properties[$parameter['name']] = [
                    "type" => $parameter['type'],
                    "description" => $parameter['description'],
                ];

                if( isset( $parameter["items"] ) ) {
                    $properties[$parameter['name']]["items"] = $parameter["items"];
                }

                if( array_key_exists( "required", $parameter ) && $parameter["required"] !== false ) {
                    $required[] = $parameter["name"];
                }
            }

            $tools[] = [
                "type" => "function",
                "function" => [
                    "name" => $function["name"],
                    "description" => $function["description"],
                    "parameters" => [
                        "type" => "object",
                        "properties" => $properties,
                        "required" => $required,
                    ],
                ],
            ];
        }

        return $tools;
    }

    public function add_function( array|callable $function ) {
        if( is_callable( $function, true ) ) {
            $function = $this->parse_function( $function );

            if( ! is_callable( $function['function'] ) ) {
                throw new \Exception( "Function must be callable (public)" );
            }
        }
        $this->functions[] = $function;
    }

    protected function parse_function( array|callable $function ) {
        if( is_array( $function ) ) {
            if( ! is_callable( $function, true ) ) {
                throw new \Exception( "Invalid class method provided" );
            }

            $reflection = new ReflectionMethod( ...$function );
        } else {
            $reflection = new ReflectionFunction( $function );
        }

        $doc_comment = $reflection->getDocComment() ?: "";
        $description = $this->parse_description( $doc_comment );

        $function_data = [
            "function" => $function,
            "name" => $reflection->getName(),
            "description" => $description,
            "parameters" => [],
        ];

        $matches = [];
        preg_match_all( '/@param\s+(\S+)\s+\$(\S+)[^\S\r\n]?([^\r\n]+)?/', $doc_comment, $matches );

        $types = $matches[1];
        $names = $matches[2];
        $descriptions = $matches[3];

        $params = $reflection->getParameters();
        foreach( $params as $param ) {
            $name = $param->getName();
            $index = array_search( $name, $names );
            $description = $descriptions[$index] ?? "";
            $type = $param->getType()?->getName() ?? $types[$index] ?? "string";

            try {
                $param->getDefaultValue();
                $required = false;
            } catch( \ReflectionException $e ) {
                $required = true;
            }

            $data = [
                "name" => $name,
                "type" => $this->parse_type( $type ),
                "description" => $description,
                "required" => $required,
            ];

            if( strpos( $type, "array<" ) === 0 ) {
                $array_type = trim( substr( $type, 5 ), "<>" );
                $data["type"] = "array";
                $data["items"] = [
                    "type" => $this->parse_type( $array_type ),
                ];
            }

            if( strpos( $type, "[]" ) !== false ) {
                $array_type = substr( $type, 0, -2 );
                $data["type"] = "array";
                $data["items"] = [
                    "type" => $this->parse_type( $array_type ),
                ];
            }

            $function_data["parameters"][] = $data;
        }

        return $function_data;
    }

    protected function parse_type( string $type ) {
        return match( $type ) {
            "int" => "number",
            "integer" => "number",
            "string" => "string",
            "float" => "number",
            default => "string",
        };
    }

    protected function parse_description( string $doc_comment ) {
        $lines = explode( "\n", $doc_comment );
        $description = "";

        $started = false;
        foreach( $lines as $line ) {
            $matches = [];
            if( preg_match( '/\s+?\*\s+?([^@](.*?))?$/', $line, $matches ) === 1 ) {
                $description .= ($matches[1] ?? "") . "\n";
                $started = true;
            } elseif( $started ) {
                break;
            }
        }

        return trim( $description );
    }

    public function messages() {
        return $this->messages;
    }

    public function loadfunction( callable $loadfunction, bool $autoload = true ) {
        $this->loadfunction = $loadfunction;
        if( $autoload && ! $this->loaded ) {
            $this->load();
        }
    }

    public function savefunction( callable $savefunction ) {
        $this->savefunction = $savefunction;
    }

    protected function openai_api_post(
        string $url,
        string|array $postfields = "",
        array $extra_headers = [],
        bool $post = true,
    ): stdClass {
        $ch = curl_init( $url );

        $headers = [
            "Content-Type: application/json",
            "Authorization: Bearer " . $this->api_key,
            ...$extra_headers,
        ];

        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );

        curl_setopt( $ch, CURLOPT_POST, $post );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

        if( $post ) {
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $postfields );
        }

        $response = curl_exec( $ch );

        curl_close( $ch );

        $data = json_decode( $response );

        if( ! isset( $data->id ) && ! isset( $data->data ) ) {
            if( isset( $data->error ) ) {
                throw new \Exception( "Error in OpenAI request: " . $data->error->message );
            }

            throw new \Exception( "Error in OpenAI request: " . $data );
        }

        return $data;
    }

    public function create_assistant(
        string $model,
        string $name = "",
        string $instructions = "",
        array $functions = [],
    ): Assistant {
        foreach( $functions as $i => $function ) {
            $functions[$i] = $this->parse_function( $function );
        }

        $tools = $this->get_functions( $functions );

        $response = $this->openai_api_post(
            url: "https://api.openai.com/v1/assistants",
            extra_headers: ["OpenAI-Beta: assistants=v1"],
            postfields: json_encode( [
                "model" => $model,
                "name" => $name,
                "instructions" => $instructions,
                "tools" => $tools,
            ] )
        );

        return new Assistant(
            name: $response->name,
            model: $response->model,
            tools: $response->tools,
            id: $response->id,
        );
    }

    public function create_thread(): Thread {
        $response = $this->openai_api_post(
            url: "https://api.openai.com/v1/threads",
            extra_headers: ["OpenAI-Beta: assistants=v1"],
        );

        return new Thread(
            id: $response->id,
        );
    }

    public function create_run(
        string $thread_id,
        string $assistant_id,
    ): Run {
        $response = $this->openai_api_post(
            url: "https://api.openai.com/v1/threads/".$thread_id."/runs",
            extra_headers: ["OpenAI-Beta: assistants=v1"],
            postfields: json_encode( [
                "assistant_id" => $assistant_id,
            ] )
        );

        return new Run(
            thread_id: $thread_id,
            required_action: $response->required_action ?? null,
            status: $response->status,
            id: $response->id,
        );
    }

    public function fetch_run(
        string $thread_id,
        string $run_id,
    ): Run {
        $response = $this->openai_api_post(
            url: "https://api.openai.com/v1/threads/" . $thread_id . "/runs/" . $run_id,
            extra_headers: ["OpenAI-Beta: assistants=v1"],
            post: false,
        );

        return new Run(
            thread_id: $thread_id,
            required_action: $response->required_action ?? null,
            status: $response->status,
            id: $response->id,
        );
    }

    public function fetch_assistant( string $assistant_id ): Assistant {
        $response = $this->openai_api_post(
            url: "https://api.openai.com/v1/assistants/" . $assistant_id,
            extra_headers: ["OpenAI-Beta: assistants=v1"],
            post: false,
        );

        return new Assistant(
            model: $response->model,
            id: $response->id,
            tools: $response->tools,
            name: $response->name,
        );
    }

    public function get_thread_messages(
        string $thread_id,
        int $limit,
        string $order = "asc",
    ): array {
        $response = $this->openai_api_post(
            url: "https://api.openai.com/v1/threads/" . $thread_id . "/messages?limit=" . $limit . "&order=" . $order,
            extra_headers: ["OpenAI-Beta: assistants=v1"],
            post: false,
        );

        return $response->data;
    }

    public function add_assistants_message(
        stdClass $message,
    ): void {
        $this->openai_api_post(
            url: "https://api.openai.com/v1/threads/" . $this->thread_id . "/messages",
            extra_headers: ["OpenAI-Beta: assistants=v1"],
            postfields: json_encode( [
                "role" => $message->role,
                "content" => $message->content,
            ] )
        );
    }

    public function submit_tool_outputs(
        string $thread_id,
        string $run_id,
        array $tool_call_outputs,
    ): void {
        $tool_outputs = [];

        foreach( $tool_call_outputs as $tool_call_id => $tool_call_output ) {
            $tool_outputs[] = [
                "tool_call_id" => $tool_call_id,
                "output" => $tool_call_output,
            ];
        }

        $this->openai_api_post(
            url: "https://api.openai.com/v1/threads/".$thread_id."/runs/".$run_id."/submit_tool_outputs",
            extra_headers: ["OpenAI-Beta: assistants=v1"],
            postfields: json_encode( [
                "tool_outputs" => $tool_outputs
            ] )
        );
    }
}
