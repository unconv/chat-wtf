<?php
class Message {
    public function __construct(
        public string $role,
        public ?string $content = null,
        public ?string $function_name = null,
        public ?string $function_arguments = null,
    ) {
        if( in_array( $role, [
            "user",
            "assistant",
            "function",
        ] ) ) {
            if( $content === null ) {
                throw new \Exception( sprintf( "Content is required for message of role '%s'", $role ) );
            }
        }
    }
}
