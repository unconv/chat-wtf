<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Chat Website</title>
    <link rel="stylesheet" href="style.css" type="text/css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/styles/default.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/highlight.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/showdown@2.1.0/dist/showdown.min.js"></script>
</head>
<body>
    <h1 id="header">ChatWTF</h1>
    <button class="clear-chat">Clear chat</button>
    <div id="chat-messages">
        <?php
        $chat_history = $_SESSION['context'] ?? [];

        foreach( $chat_history as $chat_message ) {
            $direction = $chat_message['role'] === "user" ? "outgoing" : "incoming";
            echo '<div class="chat-message '.$direction.'-message">'.htmlspecialchars( $chat_message['content'] ).'</div>';
        }
        ?>
    </div>
    <textarea id="message-input"></textarea>
    <script src="script.js"></script>
</body>
</html>
