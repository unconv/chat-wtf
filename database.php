<?php
function get_db(): PDO|null {
    $settings = require( __DIR__ . "/settings.php" );

    if( $settings['storage_type'] === "session" ) {
        return null;
    }

    $db = new PDO(
        $settings["db"]["dsn"],
        $settings["db"]["username"] ?? null,
        $settings["db"]["password"] ?? null
    );

    $db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

    return $db;
}

function get_conversation_class( $db ): ConversationInterface {
    $settings = require( __DIR__ . "/settings.php" );

    $conversation_class = [
        "session" => SessionConversation::class,
        "sql" => SQLConversation::class,
    ];

    return new $conversation_class[$settings['storage_type']]( $db );
}
