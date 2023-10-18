<?php
header( "Content-Type: application/json; charset=utf-8" );

$settings = require( __DIR__ . "/settings.php" );

if( ( $settings["speech_enabled"] ?? false ) !== true ) {
    die( json_encode( [
        "status" => "ERROR",
        "response" => "Speech not enabled",
    ] ) );
}

$speech_dir = __DIR__ . "/speech";
$input_dir = $speech_dir . "/input";
$output_dir = $speech_dir . "/output";

// clean out old output files
$old_output = glob( $speech_dir . "/output/*.wav" );
foreach( $old_output as $file ) {
    if( filemtime( $file ) < time() - 60 * 5 ) {
        @unlink( $file );
    }
}

if( ! isset( $_POST['text'] ) ) {
    die( json_encode( [
        "status" => "ERROR",
        "response" => "No text prodived",
    ] ) );
}

$text = $_POST['text'];

if( ! file_exists( $input_dir ) ) {
    if( ! @mkdir( $input_dir ) ) {
        die( json_encode( [
            "status" => "ERROR",
            "response" => "Unable to creat input directory",
        ] ) );
    }
}

if( ! file_exists( $output_dir ) ) {
    if( ! @mkdir( $output_dir ) ) {
        die( json_encode( [
            "status" => "ERROR",
            "response" => "Unable to creat output directory",
        ] ) );
    }
}

$id = uniqid( more_entropy: true );

$input_file = $input_dir . "/" . $id . ".txt";
$output_file = $output_dir . "/" . $id . ".wav";

$write = file_put_contents( $input_file, trim( $text ) );

if( $write === false ) {
    die( json_encode( [
        "status" => "ERROR",
        "response" => "Unable to write to input file",
    ] ) );
}

$speech_script = $speech_dir . "/generate.py";

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

die( json_encode( [
    "status" => "OK",
    "response" => basename( $output_file ),
] ) );
