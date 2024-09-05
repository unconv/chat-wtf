<?php
$settings = require( __DIR__ . "/settings.php" );
require( __DIR__ . "/database.php" );
require( __DIR__ . "/autoload.php" );

$db = get_db();
$conversation_class = get_conversation_class( $db );

$chat_id = intval( $_GET['chat_id'] ?? 0 );

$conversation = $conversation_class->find( $chat_id, $db );

if( ! $conversation ) {
    $chat_id = 0;
}

$new_chat = ! $chat_id;

$base_uri = $settings['base_uri'] ?? "";

if( $base_uri != "" ) {
    $base_uri = rtrim( $base_uri, "/" ) . "/";
}

$speech_enabled = ( $settings['speech_enabled'] ?? false ) === true;

$current_model = $conversation?->get_model() ?? $settings['model'];
$current_mode = $conversation?->get_mode() ?? "normal";

if( empty( $current_mode ) ) {
    $current_mode = "normal";
}

$mode_icons = [
    "normal" => "message",
    "speech" => "volume-high",
    "code_interpreter" => "terminal",
];

$current_mode_icon = $mode_icons[$current_mode];

$mode_names = [
    "normal" => "",
    "speech" => "(Speech)",
    "code_interpreter" => "(CodeInterpreter)",
];

$current_mode_name = $mode_names[$current_mode];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo $base_uri; ?>assets/css/style.css" type="text/css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/styles/stackoverflow-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/highlight.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/showdown@2.1.0/dist/showdown.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <title>ChatWTF</title>
    <script>
        let base_uri = '<?php echo $base_uri; ?>';
        let chat_id = <?php echo intval( $chat_id ); ?>;
        let new_chat = <?php echo $new_chat ? "true" : "false"; ?>;
        let speech_enabled = <?php echo $speech_enabled ? "true" : "false"; ?>;
        let chatgpt_model = '<?php echo $current_model; ?>';
        let selected_mode = '<?php echo $current_mode; ?>';
    </script>
</head>
<body>
    <nav id="sidebar">
        <div class="float-top">
            <div class="sidebar-controls">
                <button class="new-chat"><i class="fa fa-plus"></i> New chat</button>
                <button class="hide-sidebar"><i class="fa fa-chevron-left"></i></button>
            </div>
            <ul class="conversations">
                <?php
                $chats = $conversation_class->get_chats( $db );

                foreach( $chats as $chat ) {
                    $id = $chat->get_id();
                    ?>
                    <li class="">
                        <button class="conversation-button" data-id="<?php echo htmlspecialchars( $id ); ?>"><i class="fa fa-message fa-regular"></i> <span class="title-text"><?php echo htmlspecialchars( $chat->get_title() ); ?></span></button>
                        <div class="fade"></div>
                        <div class="edit-buttons">
                            <button><i class="fa fa-edit"></i></button>
                            <button class="delete" data-id="<?php echo htmlspecialchars( $id ); ?>"><i class="fa fa-trash"></i></button>
                        </div>
                    </li>
                    <?php
                }
                ?>
            </ul>
        </div>
        <div class="user-menu">
            <button>
                <i class="user-icon">u</i>
                username
                <i class="fa fa-ellipsis dots"></i>
            </button>
            <ul onclick="alert('Menu is dummy');">
                <li><button>My plan</button></li>
                <li><button>Custom instructions</button></li>
                <li><button>Settings &amp; Beta</button></li>
                <li><button>Log out</button></li>
            </ul>
        </div>
    </nav>
    <main>
        <div class="view conversation-view <?php echo $chat_id ? "show" : ""; ?>" id="chat-messages">
            <div class="model-name">
                <i class="fa fa-bolt"></i> <span class="current-model"><?php echo ( str_contains( $current_model, "gpt-4" ) ? "GPT-4" : "GPT-3.5" ) ?></span> <span class="current-mode-name"><?php echo $current_mode_name; ?></span>
            </div>
            <?php
            $chat_history = $chat_id ? $conversation->get_messages( $chat_id, $db ) : [];

            $function_result = "";
            foreach( $chat_history as $chat_message ) {
                if( $chat_message->role === "system" ) {
                    continue;
                }
                $role = htmlspecialchars( $chat_message->role );

                $classmap = [
                    "assistant" => "assistant",
                    "user" => "user",
                    "tool" => "assistant",
                    "function" => "assistant", // Backward compatibility
                    "function_call" => "assistant",
                ];

                $message_class = $classmap[$role];

                if( $message_class === "assistant" ) {
                    $user_icon_class = "gpt";
                    $user_icon_letter = "G";
                } else {
                    $user_icon_class = "";
                    $user_icon_letter = "U";
                }

                $message_content = "";
                if( $role === "function_call" ) {
                    if( $chat_message->function_name === "python" ) {
                        $code = CodeInterpreter::parse_arguments( $chat_message->function_arguments );
                        $message_content = htmlspecialchars( "I want to run the following code:\n\n```\n" . $code . "\n```" );
                    } else {
                        $message_content = htmlspecialchars( "<unknown function>" );
                    }
                } elseif(
                    $role === "tool" ||
                    $role === "function" // Backward compatibility
                ) {
                    $result_text = CodeInterpreter::parse_result( $chat_message->content );
                    $function_result = htmlspecialchars( "Result from code:\n\n```\n" . $result_text . "\n```\n\n" );
                    continue;
                } else {
                    $message_content = $function_result . htmlspecialchars( $chat_message->content );
                    $function_result = "";
                }
                ?>
                <div class="<?php echo $message_class; ?> message">
                    <div class="identity">
                        <i class="<?php echo $user_icon_class; ?> user-icon">
                            <?php echo $user_icon_letter; ?>
                        </i>
                    </div>
                    <div class="content">
                        <?php echo $message_content; ?>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
        <div class="view new-chat-view <?php echo $chat_id ? "" : "show"; ?>">
            <div class="top-menu">
                <div class="model-selector">
                    <div class="model-button button gpt-3 <?php echo ( ! str_contains( $current_model, "gpt-4" ) ? "selected" : "" ); ?>" data-model="gpt-3.5-turbo" data-name="GPT-3.5">
                        <i class="fa fa-bolt"></i> GPT-3.5
                        <div class="model-info">
                            <div class="model-info-box">
                                <p>Our fastest model, great for most every day tasks.</p>

                                <p class="secondary">Available to Free and Plus users</p>
                            </div>
                        </div>
                    </div>
                    <div class="model-button button gpt-4 <?php echo ( str_contains( $current_model, "gpt-4" ) ? "selected" : "" ); ?>" data-model="gpt-4" data-name="GPT-4">
                        <i class="fa fa-wand-magic-sparkles"></i> GPT-4
                        <div class="model-info">
                            <div class="model-info-box">
                                <p>Our most capable model, great for creative stuff.</p>

                                <p class="secondary">Available for Plus users.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
                $options = [
                    "normal" => ["Normal", "message"],
                ];
                if( ( $settings['speech_enabled'] ?? false ) === true ) {
                    $options["speech"] = ["Speech", "volume-high"];
                }
                if( ( $settings['code_interpreter']['enabled'] ?? false ) === true ) {
                    $options["code_interpreter"] = ["CodeInterpreter", "terminal"];
                }
                if( count( $options ) > 1 ) {
                    ?>
                    <div class="mode-selector button">
                        <i class="fa fa-<?php echo $current_mode_icon; ?> current-mode-icon" data-icon="<?php echo $current_mode_icon; ?>"></i>
                        <div class="mode-selector-wrap">
                            <ul>
                                <?php
                                foreach( $options as $option => $value ) {
                                    $name = htmlspecialchars( $value[0] );
                                    $icon = htmlspecialchars( $value[1] );

                                    echo '<li><button data-mode="'.htmlspecialchars( $option ).'" data-icon="'.$icon.'"><i class="fa fa-'.$icon.'"></i> '.$name.'</button></li>';
                                }
                                ?>
                            </ul>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>

            <div class="logo">
                ChatWTF
            </div>
        </div>

        <div id="message-form">
            <div class="message-wrapper">
                <textarea id="message" rows="1" placeholder="Send a message"></textarea>
                <button id="send-button"><i class="fa fa-paper-plane"></i></button>
            </div>
            <div class="disclaimer">ChatWTF uses the OpenAI ChatGPT API but is not affiliated with OpenAI</div>
        </div>
    </main>
    <script src="<?php echo $base_uri; ?>assets/js/script.js"></script>
    <script src="<?php echo $base_uri; ?>assets/js/ui-script.js"></script>
</body>
</html>
