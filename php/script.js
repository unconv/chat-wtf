const message_input = document.querySelector( "#message-input" );
const message_list = document.querySelector( "#chat-messages" );

const context = [];

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
    
    fetch( "message.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: "message=" + encodeURIComponent( question ) + "&context=" + encodeURIComponent( JSON.stringify( context ) )
    } )
    .then( response => response.text() )
    .then( data => {
        const json = JSON.parse( data );
        if( json.status == "success" ) {
            update_message( message, json.message );
            context.push([question, json.raw_message]);
        }
        message_input.focus();
    } );
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
    message.innerHTML = '<p>' + new_message + "</p>";
    message_list.scrollTop = message_list.scrollHeight;
    hljs.highlightAll();
}
