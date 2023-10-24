<?php
class SessionConversation implements ConversationInterface
{
    protected int $chat_id;
    protected string $title;
    protected string $mode;
    protected string $model;

    public function __construct() {
        self::init_session();

        if( empty( $_SESSION['chats'] ) ) {
            $_SESSION['chats'] = [];
        }
    }

    protected static function init_session() {
        if( session_status() !== PHP_SESSION_ACTIVE ) {
            session_start();
        }
    }

    /**
     * @return array<self>
     */
    public function get_chats(): array {
        self::init_session();
        $chats = $_SESSION['chats'] ?? [];
        $chats = array_reverse( $chats );

        $list = [];

        foreach( $chats as $data ) {
            $conversation = new self();
            $conversation->set_id( $data['id'] );
            $conversation->set_title( $data['title'] );
            $conversation->set_mode( $data['mode'] );
            $conversation->set_model( $data['model'] );

            $list[] = $conversation;
        }

        return $list;
    }

    public function find( int $chat_id  ): self|null {
        self::init_session();
        $data = $_SESSION['chats'][$chat_id] ?? [];

        if( empty( $data ) ) {
            return null;
        }

        $conversation = new self();
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

        return $_SESSION['chats'][$this->chat_id]["messages"] ?? [];
    }

    public function add_message( Message $message ): bool {
        $_SESSION['chats'][$this->chat_id]["messages"][] = $message;
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
            $this->chat_id = count( $_SESSION['chats'] ) + 1;
            $_SESSION['chats'][$this->chat_id] = [
                "id" => $this->chat_id,
                "title" => $this->title,
                "mode" => $this->mode,
                "model" => $this->model,
                "messages" => [],
            ];
        } else {
            $_SESSION['chats'][$this->chat_id] = [
                "id" => $this->chat_id,
                "title" => $this->title,
                "mode" => $this->mode,
                "model" => $this->model,
                "messages" => $this->get_messages(),
            ];
        }

        return $this->chat_id;
    }

    public function delete(): void {
        unset( $_SESSION['chats'][$this->chat_id] );
    }
}
