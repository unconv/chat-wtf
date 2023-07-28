<?php
function get_db(): PDO {
    $settings = require( __DIR__ . "/settings.php" );

    $db = new PDO(
        $settings["db"]["dsn"],
        $settings["db"]["username"] ?? null,
        $settings["db"]["password"] ?? null
    );

    $db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

    return $db;
}

function chat_exists( int $chat_id, PDO $db ): bool {
    $stmt = $db->prepare( "SELECT id FROM conversations WHERE id = :chat_id" );
    $stmt->execute( [
        ":chat_id" => $chat_id,
    ] );
    return !!$stmt->fetchColumn();
}

function get_chats( PDO $db ): array {
    $stmt = $db->query( "SELECT id, title FROM conversations ORDER BY id DESC" );
    return $stmt->fetchAll( PDO::FETCH_ASSOC );
}

function get_messages( int $chat_id, PDO $db ): array {
    $stmt = $db->prepare( "SELECT * FROM messages WHERE `conversation` = :chat_id" );
    $stmt->execute( [
        ":chat_id" => $chat_id,
    ] );

    return $stmt->fetchAll( PDO::FETCH_ASSOC );
}

function add_message( $message, int $chat_id, PDO $db ): bool {
    $stmt = $db->prepare( "
        INSERT INTO messages (
            `role`,
            `content`,
            `conversation`,
            `timestamp`
        ) VALUES (
            :the_role,
            :the_content,
            :the_conversation,
            :the_timestamp
        )"
    );
    $stmt->execute( [
        ":the_role" => $message['role'],
        ":the_content" => $message['content'],
        ":the_conversation" => $chat_id,
        ":the_timestamp" => date( "Y-m-d H:i:s" ),
    ] );

    return true;
}

function create_conversation( string $title, PDO $db ): int {
    $stmt = $db->prepare( "
        INSERT INTO conversations (
            title
        ) VALUES (
            :title
        )"
    );

    $stmt->execute( [
        ":title" => $title,
    ] );

    return $db->lastInsertId();
}

function set_conversation_title( string $title, int $chat_id, PDO $db ): void {
    $stmt = $db->prepare( "UPDATE conversations SET title = :title WHERE id = :chat_id LIMIT 1" );
    $stmt->execute( [
        ":title" => $title,
        ":chat_id" => $chat_id
    ] );
}

function delete_chat( int $chat_id, PDO $db ): void {
    $stmt = $db->prepare( "DELETE FROM messages WHERE conversation = :chat_id" );
    $stmt->execute([
        ":chat_id" => $chat_id,
    ]);

    $stmt = $db->prepare( "DELETE FROM conversations WHERE id = :chat_id LIMIT 1" );
    $stmt->execute([
        ":chat_id" => $chat_id,
    ]);
}
