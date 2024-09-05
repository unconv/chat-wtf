const sidebar = document.querySelector("#sidebar");
const hide_sidebar = document.querySelector(".hide-sidebar");
const new_chat_button = document.querySelector(".new-chat");

hide_sidebar.addEventListener( "click", function() {
    sidebar.classList.toggle( "hidden" );
} );

const user_menu = document.querySelector(".user-menu ul");
const show_user_menu = document.querySelector(".user-menu button");

show_user_menu.addEventListener( "click", function() {
    if( user_menu.classList.contains("show") ) {
        user_menu.classList.toggle( "show" );
        setTimeout( function() {
            user_menu.classList.toggle( "show-animate" );
        }, 200 );
    } else {
        user_menu.classList.toggle( "show-animate" );
        setTimeout( function() {
            user_menu.classList.toggle( "show" );
        }, 50 );
    }
} );

const models = document.querySelectorAll(" .model-button");

for( const model of models ) {
    model.addEventListener("click", () => {
        document.querySelector(".model-button.selected")?.classList.remove("selected");
        model.classList.add("selected");
        chatgpt_model = model.getAttribute("data-model");
        chatgpt_model_name = model.getAttribute("data-name");
        document.querySelectorAll( ".current-model" ).forEach( (e) => {
            e.textContent = chatgpt_model_name;
        } );
    });
}

const message_box = document.querySelector("#message");

message_box.addEventListener("keyup", function() {
    message_box.style.height = "auto";
    let height = message_box.scrollHeight + 2;
    if( height > 200 ) {
        height = 200;
    }
    message_box.style.height = height + "px";
});

function show_view( view_selector ) {
    document.querySelectorAll(".view").forEach(view => {
        view.style.display = "none";
    });

    document.querySelector(view_selector).style.display = "flex";
}

new_chat_button.addEventListener("click", function() {
    location.href=base_uri + "/";
});

document.querySelectorAll(".conversation-button").forEach(button => {
    button.addEventListener("click", function() {
        location.href = base_uri + "index.php?chat_id=" + button.getAttribute("data-id");
    })
});
