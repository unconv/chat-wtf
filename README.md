***2023-04-30:** Added chat history persistence with sessions*  
***2023-04-23:** Upgraded to the streaming version of the API (output token-by-token!)*  
***2023-03-16:** The PHP version of the chatbot has been updated to the ChatGPT model!*

# ChatWTF chatbot

This ChatGPT-like chatbot was made using the OpenAI API for a YouTube video I made. You can watch the original video here: https://www.youtube.com/watch?v=ru5m-BKDn6E

Video of update to ChatGPT API: https://www.youtube.com/watch?v=0NrIv6bI5o4

## PHP, HTML, JS + CSS version

There is a PHP, HTML, JS + CSS version that you can open in the browser. The entry point for this version is `php/index.html`. It will send requests to `message.php` to get the response from the OpenAI API.

The PHP version uses the `gpt-3.5-turbo` engine which is the ChatGPT engine.

## Python version

There is a simple Python version of the chatbot in `python/chat.py`. You can run it from the command line and it will ask for a question and then give you an answer. You can exit by typing `exit` as the question (or with `Ctrl+C`)

The Python version uses the `text-davinci-003` engine. It was made in the first version of the repo, and since abandoned (at least for now)

## API key

You will need an API key from OpenAI to use the code. The API key must be added to the `settings.sample.php` file, which you will need to rename to `settings.php`.

In the Python version The API key must be put in a file called api_key.txt in the same folder as the script.

## Modify to your liking

You can change the system message in the PHP version and the default prompt and default questions and answers in the Python version to make the chatbot do what you want. For example you can add a product / website documentation as the default prompt / system message and it will be able to answer questions based on the documentation.

Note that a long documentation or a long question will use up more tokens and therefore cost more. There is also a limit for the amount of tokens you can use in one request.

## Support

If you like this code or use it in some useful way, consider buying me a coffee: https://www.buymeacoffee.com/unconv
