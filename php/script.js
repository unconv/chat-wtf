const message_input = document.querySelector( "#message-input" );
const message_list = document.querySelector( "#chat-messages" );

const context = [];

const markdown_converter = new showdown.Converter();

message_input.addEventListener( "keyup", function( e ) {
    if( e.keyCode == 13 && !e.shiftKey ) {
        add_message( "outgoing", message_input.value );
        send_message();
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
        "/message.php?message=" + encodeURIComponent( question ) +
        "&context=" + encodeURIComponent( JSON.stringify( context ) )
    );

    // intitialize ChatGPT response
    let response = "";

    // when a new token arrives
    eventSource.addEventListener( "message", function( event ) {
        // append token to response
        response += event.data;

        // update message in UI
        update_message( message, response );
    } );

    eventSource.addEventListener( "stop", function( event ) {
        eventSource.close();

        // add question and answer to context
        context.push([question, response]);

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
    // replace \n with newline
    // @todo: fix interpreting literal "\n"
    new_message = new_message.replace( /\\n/g, "\n" );

    // add ending code block tags when missing
    let code_block_count = (new_message.match(/```/g) || []).length;
    if( code_block_count % 2 !== 0 ) {
        new_message += "\n```";
    }

    // convert markdown to HTML
    new_message = markdown_converter.makeHtml( new_message );

    // update message content
    message.innerHTML = '<p>' + new_message + "</p>";

    // add code highlighting
    hljs.highlightAll();
}
