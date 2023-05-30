<?php
$settings = require( __DIR__ . "/settings.php" );
session_start();

$new_chat = ! isset( $_GET['chat_id'] );
$chat_id = $_GET['chat_id'] ?? uniqid();

$base_uri = $settings['base_uri'] ?? "";

if( $base_uri != "" ) {
    $base_uri = rtrim( $base_uri, "/" ) . "/";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Chat Website</title>
    <link rel="stylesheet" href="<?php echo $base_uri; ?>style.css" type="text/css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/styles/default.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/highlight.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/showdown@2.1.0/dist/showdown.min.js"></script>
    <script>
        let base_uri = '<?php echo $base_uri; ?>';
        let chat_id = '<?php echo $chat_id; ?>';
        let new_chat = <?php echo $new_chat ? "true" : "false"; ?>;
    </script>
</head>
<body>
    <h1 id="header">ChatWTF</h1>
    <div id="wrapper">
        <div id="sidebar">
            <ul>
                <li class="new-chat"><a href="<?php echo $base_uri; ?>/">+ New chat</a></li>
            <?php
            $chats = $_SESSION['chats'] ?? [];

            foreach( $chats as $id => $chat ) {
                $link = $base_uri.'/index.php?chat_id='.htmlspecialchars( $id );
                $title = $chat['title'] ?? $id;
                $delete_button = '<button class="delete" data-id="' . $id . '">X</button>';
                echo '<li><a href="'.$link.'" title="' . htmlspecialchars( $title ) . '">'.htmlspecialchars( $title ).'</a>' . $delete_button . '</li>';
            }
            ?>
            </ul>
        </div>
        <div id="chat-messages">
            <?php
            $chat_history = $_SESSION['chats'][$chat_id]['messages'] ?? [];

            foreach( $chat_history as $chat_message ) {
                $direction = $chat_message['role'] === "user" ? "outgoing" : "incoming";
                echo '<div class="chat-message '.$direction.'-message">'.htmlspecialchars( $chat_message['content'] ).'</div>';
            }
            ?>
        </div>
    </div>
    <textarea id="message-input"></textarea>
    <script src="<?php echo $base_uri; ?>script.js"></script>
</body>
</html>
