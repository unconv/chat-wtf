const message_input = document.querySelector( "#message-input" );
const message_list = document.querySelector( "#chat-messages" );
const new_chat_link = document.querySelector( "li.new-chat" );

const markdown_converter = new showdown.Converter({
    requireSpaceBeforeHeadingText: true,
    tables: true
});

// detect Enter on message input to send message
message_input.addEventListener( "keydown", function( e ) {
    if( e.keyCode == 13 && !e.shiftKey ) {
        e.preventDefault();
        add_message( "outgoing", escapeHtml( message_input.value ) );
        send_message();
        return false;
    }
} );

/**
 * Creates a title for a conversation
 * based on user's question and
 * ChatGPT's answer
 * 
 * @param {string} question User's question
 * @param {string} answer ChatGPT's answer
 * @param {HTMLElement} title_link Title link element
 * @param {string} chat_id ID of the conversation
 */
function create_title( question, answer, title_link, chat_id ) {
    const data = new FormData();
    data.append('question', question);
    data.append('answer', answer);
    data.append('chat_id', chat_id);

    fetch( base_uri + "create_title.php", {
        method: 'POST',
        body: data
    })
    .then(response => response.text())
    .then(responseText => {
        title_link.textContent = responseText;
        title_link.setAttribute( "title", responseText );
    })
    .catch(error => {
        console.log(error);
    });
}

/**
 * Sends a message to ChatGPT and appends the
 * message and the response to the chat
 */
function send_message() {
    let question = message_input.value;

    // intialize message with blinking cursor
    let message = add_message( "incoming", '<div id="cursor"></div>' );

    // empty the message input field
    message_input.value = "";
    
    // send message
    let data = new FormData();
    data.append( "chat_id", chat_id );
    data.append( "message", question );
    fetch( base_uri + "message.php", {
        method: "POST",
        body: data
    } ).then( () => {
        // listen for response tokens
        const eventSource = new EventSource(
            base_uri + "message.php?chat_id=" + chat_id
        );

        // handle errors
        eventSource.addEventListener( "error", function() {
            update_message( message, "Sorry, there was an error in the request. Check your error logs." );
        } );

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

            if( new_chat ) {
                let title_link = create_chat_link( chat_id );

                create_title( question, response, title_link, chat_id );

                new_chat = false;
            }
        } );
    } );

    message_input.focus();
}

/**
 * Creates a new chat conversation link
 * to the sidebar
 *
 * @returns Title link element
 */
function create_chat_link() {
    let link = base_uri + "index.php?chat_id=" + chat_id;
    let title_link = document.createElement( "a" );
        title_link.setAttribute( "href", link );

    let delete_button = document.createElement( "button" );
        delete_button.setAttribute( "data-id", chat_id );
        delete_button.classList.add( "delete" );
        delete_button.textContent = "X";

    let title_box = document.createElement( "li" );
        title_box.appendChild( title_link );
        title_box.appendChild( delete_button );

    new_chat_link.appendChild( title_box );

    return title_link;
}

/**
 * Adds a message to the message list
 *
 * @param {string} direction incoming/outgoing
 * @param {string} message The message to add
 * @returns The added message element
 */
function add_message( direction, message ) {
    const message_item = document.createElement( "div" );
    message_item.classList.add( "chat-message" );
    message_item.classList.add( direction+"-message" );
    message_item.innerHTML = '<p>' + message + "</p>";
    message_list.appendChild( message_item );
    message_list.scrollTop = message_list.scrollHeight;

    // add code highlighting
    message_item.querySelectorAll('pre code').forEach( (el) => {
        hljs.highlightElement(el);
    } );

    return message_item;
}

/**
 * Updates a chat message with the given text
 *
 * @param {HTMLElement} message The message element to update
 * @param {string} new_message The new message
 */
function update_message( message, new_message ) {
    // convert message from Markdown to HTML
    html_message = convert_markdown( new_message );

    // update message content
    message.innerHTML  = '<p>' + html_message + '</p>';

    // add code highlighting
    message.querySelectorAll('pre code').forEach( (el) => {
        let code = el.textContent;
        hljs.highlightElement(el);

        el.appendChild(
            create_copy_button( code )
        );
    } );

    message.appendChild(
        create_copy_button( new_message )
    );
}

function create_copy_button( text_to_copy ) {
    let icon = document.createElement( "i" );
    icon.classList.add( "fa", "fa-clipboard" );

    let copy_button = document.createElement( "button" );
    copy_button.classList.add( "copy" );
    copy_button.appendChild( icon );
    copy_button.addEventListener( "click", function() {
        navigator.clipboard.writeText( text_to_copy );
        icon.classList.remove( "fa-clipboard" );
        icon.classList.add( "fa-check" );
    } );

    return copy_button;
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

// events to run when page loads
document.addEventListener( "DOMContentLoaded", function() {
    // markdown format all messages in view
    let messages = document.querySelectorAll( ".chat-message" );
    messages.forEach( function( message ) {
        update_message( message, message.textContent );
    } );

    // event listeners
    document.body.addEventListener( "click", function(e) {
        if( e.target.matches( "button.delete" ) ) {
            delete_button_action( e );
        }
    } );
} );

/**
 * Handles the chat conversation delete button click
 *
 * @param {Event} e The delete button click event
 */
function delete_button_action( e ) {
    if( ! confirm( "Are you sure you want to delete this conversation?" ) ) {
        return;
    }

    const chat_id = e.target.getAttribute( "data-id" );
    const data = new FormData();
    data.append( "chat_id", chat_id );
    // @todo: add error handling
    fetch( base_uri + "delete_chat.php", {
        method: 'POST',
        body: data
    } );
    e.target.parentNode.remove();
}
