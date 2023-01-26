# ChatWTF chatbot

This ChatGPT-like chatbot was made using the OpenAI API for a YouTube video I made. You can watch the video here: https://www.youtube.com/watch?v=ru5m-BKDn6E

## Python version

There is a simple Python version of the chatbot in `python/chat.py`. You can run it from the command line and it will ask for a question and then give you an answer. You can exit by typing `exit` as the question (or with `Ctrl+C`)

## PHP, HTML, JS + CSS version

There is also a PHP, HTML, JS + CSS version that you can open in the browser. The entry point for this version is `php/index.html`. It will send requests to `message.php` to get the response from the OpenAI API.

## API key

You will need an API key from OpenAI to use the code. The API key must be put in a file called api_key.txt in the same folder as the script.

## Modify to your liking

You can change the default prompt and default questions and answers in the code to make the chatbot do what you want. For example you can add a product / website documentation as the default prompt and it will be able to answer questions based on the documentation.

Note that a long documentation or a long question will use up more tokens and therefore cost more. There is also a limit for the amount of tokens you can use in one request.

## Support

If you like this code or use it in some useful way, consider buying me a coffee: https://www.buymeacoffee.com/unconv
