<?php
interface ConversationInterface
{
    /**
     * @return array<self>
     */
    public function get_chats(): array;

    public function find( int $chat_id  ): self|null;

    /**
     * @return array<Message>
     */
    public function get_messages(): array;

    public function add_message( Message $message ): bool;

    public function set_id( string $id );

    public function set_title( string $title );

    public function set_mode( string $mode );

    public function set_model( string $model );

    public function get_id();

    public function get_title();

    public function get_mode();

    public function get_model();

    public function save(): int;

    public function delete(): void;
}
