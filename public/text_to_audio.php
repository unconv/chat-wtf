<?php
header( "Content-Type: application/json; charset=utf-8" );

$settings = require( __DIR__ . "/../settings.php" );

if( ( $settings["speech_enabled"] ?? false ) !== true ) {
    die( json_encode( [
        "status" => "ERROR",
        "response" => "Speech not enabled",
    ] ) );
}

if( ! isset( $_POST['text'] ) ) {
    die( json_encode( [
        "status" => "ERROR",
        "response" => "No text prodived",
    ] ) );
}

$text = $_POST['text'];

$input_file = tempnam( sys_get_temp_dir(), "cwtftext" );
$output_file = tempnam( sys_get_temp_dir(), "cwtfaudio" );

$write = file_put_contents( $input_file, trim( $text ) );

if( $write === false ) {
    die( json_encode( [
        "status" => "ERROR",
        "response" => "Unable to write to input file",
    ] ) );
}

$speech_script = __DIR__ . "/../speech/generate.py";

exec( "python3 " . escapeshellarg( $speech_script ) . " " . escapeshellarg( $input_file ) . " " . escapeshellarg( $output_file ) . " " . escapeshellarg( $settings['elevenlabs_api_key'] ), $output, $result_code );

unlink( $input_file );

if( ! file_exists( $output_file ) ) {
    die( json_encode( [
        "status" => "ERROR",
        "response" => "Unable to create output file",
        "output" => implode( "\n", $output ),
        "result_code" => $result_code,
    ] ) );
}

$b64_audio = base64_encode( file_get_contents( $output_file ) );

unlink( $output_file );

die( json_encode( [
    "status" => "OK",
    "response" => $b64_audio,
] ) );
