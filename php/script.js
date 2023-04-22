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
    let message = add_message( "incoming", '<div id="cursor"></div>' );
    message_input.value = "";
    
    const eventSource = new EventSource( "/message.php?message=" + encodeURIComponent( question ) + "&context=" + encodeURIComponent( JSON.stringify( context ) ) );

    let response = "";

    eventSource.addEventListener( "message", function( event ) {
        response += event.data;
        update_message( message, response );
    } );

    eventSource.addEventListener( "stop", function( event ) {
        eventSource.close();
        context.push([question, response]);
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
    new_message = new_message.replace( /\\n/g, "\n" );

    let code_block_count = (new_message.match(/```/g) || []).length;
    if( code_block_count % 2 !== 0 ) {
        new_message += "\n```";
    }

    new_message = markdown_converter.makeHtml( new_message );
    message.innerHTML = '<p>' + new_message + "</p>";
    message_list.scrollTop = message_list.scrollHeight;
    hljs.highlightAll();
}
