const message_input = document.querySelector( "#message-input" );
const message_list = document.querySelector( "#chat-messages" );

const markdown_converter = new showdown.Converter({
    requireSpaceBeforeHeadingText: true
});

message_input.addEventListener( "keydown", function( e ) {
    if( e.keyCode == 13 && !e.shiftKey ) {
        e.preventDefault();
        add_message( "outgoing", escapeHtml( message_input.value ) );
        send_message();
        return false;
    }
} );

function send_message() {
    let question = message_input.value;

    // intialize message with blinking cursor
    let message = add_message( "incoming", '<div id="cursor"></div>' );

    // empty the message input field
    message_input.value = "";
    
    // send message and listen for tokens
    // @todo: send message as POST?
    const eventSource = new EventSource(
        "/message.php?message=" + encodeURIComponent( question )
    );

    // intitialize ChatGPT response
    let response = "";

    // when a new token arrives
    eventSource.addEventListener( "message", function( event ) {
        let json = JSON.parse( event.data );

        // append token to response
        response += json.content;

        // update message in UI
        update_message( message, response );
    } );

    eventSource.addEventListener( "stop", function( event ) {
        eventSource.close();

        // scroll to bottom of chat
        // @todo: scroll while new tokens are added
        //        (only if user didn't scroll up)
        message_list.scrollTop = message_list.scrollHeight;
    } );

    message_input.focus();
}

function add_message( direction, message ) {
    const message_item = document.createElement( "div" );
    message_item.classList.add( "chat-message" );
    message_item.classList.add( direction+"-message" );
    message_item.innerHTML = '<p>' + message + "</p>";
    message_list.appendChild( message_item );
    message_list.scrollTop = message_list.scrollHeight;
    hljs.highlightAll();
    return message_item;
}

function update_message( message, new_message ) {
    // convert message from Markdown to HTML
    new_message = convert_markdown( new_message );

    // update message content
    message.innerHTML = '<p>' + new_message + "</p>";

    // add code highlighting
    hljs.highlightAll();
}

/**
 * Converts Markdown formatted response into HTML
 *
 * @param {string} text Markdown formatted text
 * @returns HTML formatted text
 */
function convert_markdown( text ) {
    // add ending code block tags when missing
    let code_block_count = (text.match(/```/g) || []).length;
    if( code_block_count % 2 !== 0 ) {
        text += "\n```";
    }

    // HTML-escape parts of text that are not inside ticks.
    // This prevents <?php from turning into a comment tag
    let escaped_parts = [];
    let code_parts = text.split("`");
    for( let i = 0; i < code_parts.length; i++ ) {
        if( i % 2 === 0 ) {
            escaped_parts.push( escapeHtml( code_parts[i] ) );
        } else {
            escaped_parts.push( code_parts[i] );
        }
    }
    let escaped_message = escaped_parts.join("`");

    // Convert Markdown to HTML
    let formatted_message = "";
    let code_blocks = escaped_message.split("```");
    for( let i = 0; i < code_blocks.length; i++ ) {
        if( i % 2 === 0 ) {
            // add two spaces in the end of every line
            // for non-codeblocks so that one-per-line lists
            // without markdown can be generated
            formatted_message += markdown_converter.makeHtml(
                code_blocks[i].trim().replace( /\n/g, "  \n" )
            );
        } else {
            // convert Markdown code blocks to HTML
            formatted_message += markdown_converter.makeHtml(
                "```" + code_blocks[i] + "```"
            );
        }
    }

    return formatted_message;
}

/**
 * Escapes HTML special characters in a string
 * Source: https://stackoverflow.com/questions/1787322
 *
 * @param {string} text Raw text
 * @returns string Escaped HTML
 */
function escapeHtml( text ) {
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };

    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

document.addEventListener( "DOMContentLoaded", function() {
    let messages = document.querySelectorAll( ".chat-message" );

    messages.forEach( function( message ) {
        update_message( message, message.textContent );
    } );

    let clear_chat_button = document.querySelector( ".clear-chat" );
    clear_chat_button.addEventListener( "click", function() {
        fetch( "/clear_chat.php" );
        message_list.innerHTML = '';
    } );
} );
