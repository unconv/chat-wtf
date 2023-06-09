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
    
    // send message and listen for tokens
    // @todo: send message as POST?
    const eventSource = new EventSource(
        base_uri + "message.php?chat_id="+chat_id+"&message=" + encodeURIComponent( question )
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
    new_message = convert_markdown( new_message );

    // update message content
    //message.innerHTML = '<p>' + new_message + "</p>";
    message.innerHTML = '<div class="copy-container"><button class="copy-button"><svg xmlns="http://www.w3.org/2000/svg" class="chat-clipboard-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" height="10px"><path d="M19 4h-2a2 2 0 0 0 -2 -2h-4a2 2 0 0 0 -2 2h-2a2 2 0 0 0 -2 2v16a2 2 0 0 0 2 2h14a2 2 0 0 0 2 -2v-16a2 2 0 0 0 -2 -2z"></path><rect x="9" y="2" width="6" height="4"></rect></svg></button><p>' + new_message + "</p></div>";
    

    // add code highlighting
    message.querySelectorAll('pre code').forEach( (el) => {
        hljs.highlightElement(el);
    } );
    
    message.querySelectorAll('div.copy-container').forEach( (el) => {

      el.addEventListener("click", function()
      {
        let new_message_nohtml = new_message.replace(/<[^>]*>?/gm, '').replace(/"/g, '').replace(/'/g, '');
        new_message_nohtml = decodeHTML(new_message_nohtml);
         copyToClipboard(el,new_message_nohtml);
            
        }); 

    } );
}

function decodeHTML(html) {
    var txt = document.createElement('textarea');
    txt.innerHTML = html;
    return txt.value;
  }

function copyToClipboard(el,text) {
    const button = el.querySelector("button");
    const textarea = document.createElement("textarea");
    textarea.value = text;
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand("copy");
    document.body.removeChild(textarea);
    const svgElement = button.querySelector("svg");
    const isTickSymbol = svgElement.innerHTML.includes('M3.73 11.72l6.37 6.37l14.29-14.3');

    if (!isTickSymbol) {
        svgElement.innerHTML = '<path d="M3.73 11.72l6.37 6.37l14.29-14.3"></path>';
    }
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
