<?php
class SQLConversation implements ConversationInterface
{
    protected int $chat_id;
    protected string $title;
    protected string $mode;
    protected string $model;

    public function __construct( protected PDO $db ) {

    }

    /**
     * @return array<self>
     */
    public function get_chats(): array {
        $stmt = $this->db->query( "SELECT id, title, mode, model FROM conversations ORDER BY id DESC" );
        $chats = $stmt->fetchAll( PDO::FETCH_ASSOC );

        $list = [];

        foreach( $chats as $data ) {
            $conversation = new self( $this->db );
            $conversation->set_id( $data['id'] );
            $conversation->set_title( $data['title'] );
            $conversation->set_mode( $data['mode'] );
            $conversation->set_model( $data['model'] );

            $list[] = $conversation;
        }

        return $list;
    }

    public function find( int $chat_id ): self|null {
        $stmt = $this->db->prepare( "SELECT * FROM conversations WHERE id = :chat_id" );
        $stmt->execute( [
            ":chat_id" => $chat_id,
        ] );

        $data = $stmt->fetch( PDO::FETCH_ASSOC );

        if( empty( $data ) ) {
            return null;
        }

        $conversation = new self( $this->db );
        $conversation->set_id( $data['id'] );
        $conversation->set_title( $data['title'] );
        $conversation->set_mode( $data['mode'] );
        $conversation->set_model( $data['model'] );

        return $conversation;
    }

    /**
     * @return array<Message>
     */
    public function get_messages(): array {
        if( ! isset( $this->chat_id ) ) {
            return [];
        }

        $stmt = $this->db->prepare( "SELECT * FROM messages WHERE `conversation` = :chat_id" );
        $stmt->execute( [
            ":chat_id" => $this->chat_id,
        ] );

        $messages = $stmt->fetchAll( PDO::FETCH_ASSOC );

        $message_list = [];

        foreach( $messages as $message ) {
            $message_list[] = new Message(
                role: $message["role"],
                content: $message["content"],
                function_name: $message["function_name"],
                function_arguments: $message["function_arguments"],
            );
        }

        return $message_list;
    }

    public function add_message( Message $message ): bool {
        $stmt = $this->db->prepare( "
            INSERT INTO messages (
                `role`,
                `content`,
                `function_name`,
                `function_arguments`,
                `conversation`,
                `timestamp`
            ) VALUES (
                :the_role,
                :the_content,
                :the_function_name,
                :the_function_arguments,
                :the_conversation,
                :the_timestamp
            )"
        );
        $stmt->execute( [
            ":the_role" => $message->role,
            ":the_content" => $message->content ?? "", // TODO: update database to allow NULL
            ":the_function_name" => $message->function_name,
            ":the_function_arguments" => $message->function_arguments,
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

    public function set_mode( string $mode ) {
        $this->mode = $mode;
    }

    public function set_model( string $model ) {
        $this->model = $model;
    }

    public function get_id() {
        return $this->chat_id;
    }

    public function get_title() {
        return $this->title;
    }

    public function get_mode() {
        return $this->mode;
    }

    public function get_model() {
        return $this->model;
    }

    public function save(): int {
        if( ! isset( $this->chat_id ) ) {
            $stmt = $this->db->prepare( "
                INSERT INTO conversations (
                    title,
                    mode,
                    model
                ) VALUES (
                    :title,
                    :mode,
                    :model
                )"
            );

            $stmt->execute( [
                ":title" => $this->title,
                ":mode" => $this->mode,
                ":model" => $this->model,
            ] );

            $this->chat_id = $this->db->lastInsertId();
        } else {
            $stmt = $this->db->prepare( "UPDATE conversations SET title = :title, mode = :mode, model = :model WHERE id = :chat_id LIMIT 1" );
            $stmt->execute( [
                ":title" => $this->title,
                ":mode" => $this->mode,
                ":model" => $this->model,
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
