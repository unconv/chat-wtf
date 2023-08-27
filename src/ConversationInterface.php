<?php
interface ConversationInterface
{
    /**
     * @return array<self>
     */
    public function get_chats(): array;

    public function find( int $chat_id  ): self|false;

    public function get_messages();

    public function add_message( $message ): bool;

    public function set_id( string $id );

    public function set_title( string $title );

    public function get_id();

    public function get_title();

    public function save(): int;

    public function delete(): void;
}
