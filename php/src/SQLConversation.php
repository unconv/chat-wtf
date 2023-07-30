<?php
class SQLConversation implements ConversationInterface
{
    protected int $chat_id;
    protected string $title;

    public function __construct( protected PDO $db ) {

    }

    /**
     * @return array<self>
     */
    public function get_chats(): array {
        $stmt = $this->db->query( "SELECT id, title FROM conversations ORDER BY id DESC" );
        $chats = $stmt->fetchAll( PDO::FETCH_ASSOC );

        $list = [];

        foreach( $chats as $data ) {
            $conversation = new self( $this->db );
            $conversation->set_id( $data['id'] );
            $conversation->set_title( $data['title'] );

            $list[] = $conversation;
        }

        return $list;
    }

    public function find( int $chat_id ): self|false {
        $stmt = $this->db->prepare( "SELECT * FROM conversations WHERE id = :chat_id" );
        $stmt->execute( [
            ":chat_id" => $chat_id,
        ] );

        $data = $stmt->fetch( PDO::FETCH_ASSOC );

        if( empty( $data ) ) {
            return false;
        }

        $conversation = new self( $this->db );
        $conversation->set_id( $data['id'] );
        $conversation->set_title( $data['title'] );

        return $conversation;
    }

    public function get_messages(): array {
        if( ! isset( $this->chat_id ) ) {
            return [];
        }

        $stmt = $this->db->prepare( "SELECT * FROM messages WHERE `conversation` = :chat_id" );
        $stmt->execute( [
            ":chat_id" => $this->chat_id,
        ] );

        return $stmt->fetchAll( PDO::FETCH_ASSOC );
    }

    public function add_message( $message ): bool {
        $stmt = $this->db->prepare( "
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
            ":the_conversation" => $this->chat_id,
            ":the_timestamp" => date( "Y-m-d H:i:s" ),
        ] );

        return true;
    }

    public function set_id( string $id ) {
        $this->chat_id = $id;
    }

    public function set_title( string $title ) {
        $this->title = $title;
    }

    public function get_id() {
        return $this->chat_id;
    }

    public function get_title() {
        return $this->title;
    }

    public function save(): int {
        if( ! isset( $this->chat_id ) ) {
            $stmt = $this->db->prepare( "
                INSERT INTO conversations (
                    title
                ) VALUES (
                    :title
                )"
            );

            $stmt->execute( [
                ":title" => $this->title,
            ] );

            $this->chat_id = $this->db->lastInsertId();
        } else {
            $stmt = $this->db->prepare( "UPDATE conversations SET title = :title WHERE id = :chat_id LIMIT 1" );
            $stmt->execute( [
                ":title" => $this->title,
                ":chat_id" => $this->chat_id,
            ] );
        }

        return $this->chat_id;
    }

    public function delete(): void {
        $stmt = $this->db->prepare( "DELETE FROM messages WHERE conversation = :chat_id" );
        $stmt->execute([
            ":chat_id" => $this->chat_id,
        ]);

        $stmt = $this->db->prepare( "DELETE FROM conversations WHERE id = :chat_id LIMIT 1" );
        $stmt->execute([
            ":chat_id" => $this->chat_id,
        ]);
    }
}
