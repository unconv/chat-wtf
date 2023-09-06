const message_input = document.querySelector( "#message" );
const message_list = document.querySelector( "#chat-messages" );
const new_chat_link = document.querySelector( "li.new-chat" );
const conversations_list = document.querySelector( "ul.conversations" );

const markdown_converter = new showdown.Converter({
    requireSpaceBeforeHeadingText: true,
    tables: true,
    underline: false,
});

// detect Enter on message input to send message
message_input.addEventListener( "keydown", function( e ) {
    if( e.keyCode == 13 && !e.shiftKey ) {
        e.preventDefault();
        add_message( "user", escapeHtml( message_input.value ) );
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
        title_link.querySelector(".title-text").textContent = responseText;
        title_link.setAttribute( "title", responseText );
    })
    .catch(error => {
        console.log(error);
    });
}

function scrolled_to_bottom() {
    return ( Math.ceil( message_list.scrollTop ) + message_list.offsetHeight ) >= message_list.scrollHeight;
}

/**
 * Sends a message to ChatGPT and appends the
 * message and the response to the chat
 */
async function send_message() {
    show_view( ".conversation-view" );

    let question = message_input.value;

    // intialize message with blinking cursor
    let message = add_message( "assistant", '<div id="cursor"></div>' );

    // empty the message input field
    message_input.value = "";
    
    // send message
    let data = new FormData();
    data.append( "chat_id", chat_id );
    data.append( "message", question );

    // send message and get chat id
    chat_id = await fetch( base_uri + "message.php", {
        method: "POST",
        body: data
    } ).then((response) => {
        return response.text();
    });

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

        let scrolled = scrolled_to_bottom();

        // update message in UI
        update_message( message, response );

        if( scrolled ) {
            message_list.scrollTop = message_list.scrollHeight;
        }
    } );

    eventSource.addEventListener( "stop", function( event ) {
        eventSource.close();

        if( new_chat ) {
            let title_link = create_chat_link( chat_id );

            create_title( question, response, title_link, chat_id );

            new_chat = false;
        }
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
    conversations_list.querySelector(".active")?.classList.remove("active");

    let title_link = document.createElement( "li" );
        title_link.classList.add( "active" );

    title_link.insertAdjacentHTML("afterbegin", `
        <button class="conversation-button" data-id="${chat_id}"><i class="fa fa-message fa-regular"></i> <span class="title-text">Untitled Chat</span></button>
        <div class="fade"></div>
        <div class="edit-buttons">
            <button><i class="fa fa-edit"></i></button>
            <button class="delete" data-id="${chat_id}"><i class="fa fa-trash"></i></button>
        </div>
    `);

    conversations_list.prepend(title_link);

    return title_link;
}

/**
 * Adds a message to the message list
 *
 * @param {string} role user/assistant
 * @param {string} message The message to add
 * @returns The added message element
 */
function add_message( role, message ) {
    const message_item = document.createElement("div");
    message_item.classList.add(role);
    message_item.classList.add("message");

    let user_icon_class = "";
    let user_icon_letter = "U";
    if( role === "assistant" ) {
        user_icon_letter = "G";
        user_icon_class = "gpt";
    }

    message_item.insertAdjacentHTML("beforeend", `
        <div class="identity">
            <i class="${user_icon_class} user-icon">
                ${user_icon_letter}
            </i>
        </div>
        <div class="content">
            ${message}
        </div>
    `);

    message_list.appendChild(message_item);

    // add code highlighting
    message_item.querySelectorAll('.content pre code').forEach( (el) => {
        hljs.highlightElement(el);
    } );

    // scroll down the message list
    message_list.scrollTop = message_list.scrollHeight;

    return message_item;
}

/**
 * Updates a chat message with the given text
 *
 * @param {HTMLElement} message The message element to update
 * @param {string} new_message The new message
 */
function update_message( message, new_message ) {
    const content = message.querySelector(".content");

    // convert message from Markdown to HTML
    html_message = convert_markdown( new_message );

    // update message content
    content.innerHTML  = html_message;

    // add code highlighting
    content.querySelectorAll('pre code').forEach( (el) => {
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
    text = text.trim();

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
    let messages = document.querySelectorAll( ".message" );
    messages.forEach( function( message ) {
        update_message( message, message.querySelector(".content").textContent );
    } );

    // event listeners
    document.body.addEventListener( "click", function(e) {
        const button = e.target.closest( "button.delete" );
        if( button ) {
            delete_button_action( button );
        }
    } );
} );

/**
 * Handles the chat conversation delete button click
 *
 * @param {Element} button The delete button
 */
function delete_button_action( button ) {
    if( ! confirm( "Are you sure you want to delete this conversation?" ) ) {
        return;
    }

    const chat_id = button.getAttribute( "data-id" );
    const data = new FormData();
    data.append( "chat_id", chat_id );
    // @todo: add error handling
    fetch( base_uri + "delete_chat.php", {
        method: 'POST',
        body: data
    } );
    button.parentNode.parentNode.remove();
}
