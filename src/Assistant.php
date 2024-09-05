<?php
class Assistant
{
    public function __construct(
        protected string $model,
        protected string $id,
        protected array $tools = [],
        protected string $name = "",
    ) {}

    public function get_id(): string {
        return $this->id;
    }

    public function get_functions() {
        $functions = array_filter( $this->tools, function( $tool ) {
            return $tool->type === "function";
        } );

        return array_map( fn( $tool ) => json_decode( json_encode( $tool->function ), true ), $functions );
    }
}
