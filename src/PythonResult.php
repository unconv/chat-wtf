<?php
class PythonResult {
    public function __construct(
        public string $output,
        public int $result_code,
    ) {}
}
