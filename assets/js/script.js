const message_input = document.querySelector( "#message" );
const send_button = document.querySelector( "#send-button" );
const message_list = document.querySelector( "#chat-messages" );
const new_chat_link = document.querySelector( "li.new-chat" );
const conversations_list = document.querySelector( "ul.conversations" );
const mode_buttons = document.querySelectorAll( ".mode-selector button" );
const current_mode_icon = document.querySelector( ".current-mode-icon" );
const current_mode_name = document.querySelector( ".current-mode-name" );

const mode_names = {
    "normal": "",
    "speech": "(Speech)",
    "code_interpreter": "(CodeInterpreter)"
};

mode_buttons.forEach( (button) => {
    button.addEventListener( "click", () => {
        selected_mode = button.getAttribute( "data-mode" );

        const new_icon = button.getAttribute( "data-icon" );
        const old_icon = current_mode_icon.getAttribute( "data-icon" );

        current_mode_icon.classList.remove( "fa-" + old_icon );
        current_mode_icon.classList.add( "fa-" + new_icon );
        current_mode_icon.setAttribute( "data-icon", new_icon);

        current_mode_name.textContent = mode_names[selected_mode];
    } );
} );

const markdown_converter = new showdown.Converter({
    requireSpaceBeforeHeadingText: true,
    tables: true,
    underline: false,
});

// detect Enter on message input to send message
message_input.addEventListener( "keydown", function( e ) {
    if( e.keyCode == 13 && !e.shiftKey ) {
        e.preventDefault();
        submit_message();
        return false;
    }
} );

// detect click on send button to send message
send_button.addEventListener( "click", function( e ) {
    e.preventDefault();
    submit_message();
    return false;
} );

/**
 * Submits the currently typed in message to ChatWTF
 */
function submit_message() {
    message_box.style.height = "auto";
    add_message( "user", escapeHtml( message_input.value ) );
    send_message( message_input.value );
}

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

class AudioQueue {
    queue = [];
    handling = false;

    async add( text ) {
        const audio = await create_text_to_speech( text );
        this.queue.push( audio );
        if( this.queue.length === 1 ) {
            await this.handle();
        }
    }

    async handle() {
        if( this.handling ) {
            return false;
        }

        this.handling = true;

        const audio = this.queue.shift();
        await play_audio( audio );

        if( this.queue.length > 0 ) {
            this.handling = false;
            await this.handle();
        }

        this.handling = false;
    }
}

/**
 * Sends a message to ChatGPT and appends the
 * message and the response to the chat
 */
async function send_message( message_text ) {
    show_view( ".conversation-view" );

    // intialize message with blinking cursor
    let message = add_message( "assistant", '<div id="cursor"></div>' );

    // empty the message input field
    message_input.value = "";
    
    // send message
    let data = new FormData();
    data.append( "chat_id", chat_id );
    data.append( "model", chatgpt_model );
    data.append( "mode", selected_mode );
    data.append( "message", message_text );

    // send message and get chat id
    chat_id = await fetch( base_uri + "message.php", {
        method: "POST",
        body: data
    } ).then((response) => {
        return response.text();
    });

    // listen for response tokens
    const eventSource = new EventSource(
        base_uri + "message.php?chat_id=" + chat_id + "&model=" + chatgpt_model + "&mode=" + selected_mode
    );

    // handle errors
    eventSource.addEventListener( "error", function() {
        update_message( message, "Sorry, there was an error in the request. Check your error logs." );
    } );

    // intitialize ChatGPT response
    let response = "";

    // initialize audio handling
    const audio_queue = new AudioQueue();
    let paragraph = "";

    // when a new token arrives
    eventSource.addEventListener( "message", function( event ) {
        let json = JSON.parse( event.data );

        if( json.hasOwnProperty( "role" ) && json.role === "function_call" ) {
            if( json.function_name === "python" ) {
                const args = JSON.parse( json.function_arguments );
                update_message( message, "I would like to run the following code:\n\n```\n" + args.code + "\n```" );

                message.querySelector(".content").insertAdjacentHTML( "beforeend", `
                    <div class="action-selector">
                        <button class="run-code">Run code</button>
                        <button class="dont-run-code">Don't run code</button>
                    </div>
                ` );
            }
            return;
        }

        const speech_mode = selected_mode === "speech";

        // append token to response
        response += json.content;
        paragraph += json.content;

        if( paragraph.indexOf( "\n\n" ) !== -1 ) {
            if( speech_mode && paragraph.trim() !== "" ) {
                audio_queue.add( paragraph );
            }

            paragraph = "";
        }

        let scrolled = scrolled_to_bottom();

        // update message in UI
        update_message( message, response );

        if( scrolled ) {
            message_list.scrollTop = message_list.scrollHeight;
        }
    } );

    eventSource.addEventListener( "stop", async function( event ) {
        eventSource.close();

        const speech_mode = selected_mode === "speech";

        if( new_chat ) {
            let title_link = create_chat_link( chat_id );

            create_title( message_text, response, title_link, chat_id );

            new_chat = false;
        }

        if( speech_mode && paragraph.trim() !== "" ) {
            audio_queue.add( paragraph );
            paragraph = "";
        }
    } );

    message_input.focus();
}

async function create_text_to_speech( text ) {
    return new Promise( (resolve, _) => {
        const data = new FormData();
        data.append( "text", text );

        fetch( base_uri + "text_to_audio.php", {
            method: 'POST',
            body: data
        } ).then( response => {
            return response.json();
        } ).then( data => {
            if( data.status !== "OK" ) {
                console.log( new Error( "Unable to create audio" ) );
                return;
            }

            var audio = new Audio();
            audio.src = base_uri + "speech/output/" + data.response;

            audio.addEventListener( 'canplaythrough', () => {
                 resolve( audio );
            } );
        } );
     } );
}

async function play_audio( audio ) {
   return new Promise( (resolve, _) => {
        audio.addEventListener( 'ended', () => {
            resolve();
        } );
        audio.play();
    } );
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

    // delete old copy buttons
    message.querySelectorAll("button.copy").forEach(btn => {
        btn.remove();
    });

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

function convert_links( text ) {
    text = text.replace( /\(data\//g, '(data/' + chat_id + '/data/' );
    text = text.replace( /\(sandbox:\/data\//g, '(data/' + chat_id + '/data/' );
    text = text.replace( /\(sandbox:\/mnt\/data\//g, '(data/' + chat_id + '/data/' );
    text = text.replace( /\(sandbox:data\//g, '(data/' + chat_id + '/data/' );
    return text;
}

/**
 * Converts Markdown formatted response into HTML
 *
 * @param {string} text Markdown formatted text
 * @returns HTML formatted text
 */
function convert_markdown( text ) {
    text = convert_links( text );
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

    formatted_message = formatted_message.replace( '<a href=', '<a target="_blank" href=' );

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
        const delete_button = e.target.closest( "button.delete" );
        if( delete_button ) {
            delete_button_action( delete_button );
            return;
        }

        if( e.target.closest( ".run-code" ) ) {
            add_message( "user", "Yes, run the code." );
            send_message( "Yes, run the code." );
            e.target.closest( ".action-selector" ).remove();
            return;
        }

        if( e.target.closest( ".dont-run-code" ) ) {
            add_message( "user", "No, don't run the code." );
            send_message( "No, don't run the code." );
            e.target.closest( ".action-selector" ).remove();
            return;
        }
    } );

    // scroll down the message list
    message_list.scrollTop = message_list.scrollHeight;
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
