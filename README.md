# ChatWTF Chatbot

This ChatGPT-like chatbot was made using the OpenAI API for a YouTube video I made. You can watch the original video here: https://www.youtube.com/watch?v=ru5m-BKDn6E

Video of update to ChatGPT API: https://www.youtube.com/watch?v=0NrIv6bI5o4

## Quick Start

1. Clone the repository
2. Add your OpenAI API key to `settings.php` (see `settings.sample.php`)
3. Start a server in the `php/` folder

```console
$ cd php
$ php -S localhost:8080
```

4. Go to http://localhost:8080

## Database

The chatbot uses PHP sessions to store the conversations by default. You can also use an SQL database. There is a SQLite dump and a MySQL dump in the `db` folder. You can install the SQLite version by running the `install_sqlite.php` script.

Database config has to be put into `settings.php` (see `settings.sample.php`). You need to also change `storage_type` to `sql` in the settings in order to use a database.

## API key

You will need an API key from OpenAI to use the code. The API key must be added to the `settings.sample.php` file, which you will need to rename to `settings.php`.

## Modify to your liking

You can change the system message in the PHP version and the default prompt and default questions and answers in the Python version to make the chatbot do what you want. For example you can add a product / website documentation as the default prompt / system message and it will be able to answer questions based on the documentation.

Note that a long documentation or a long question will use up more tokens and therefore cost more. There is also a limit for the amount of tokens you can use in one request.

## Python version

There is a simple Python version of the chatbot in `python/chat.py`. You can run it from the command line and it will ask for a question and then give you an answer. You can exit by typing `exit` as the question (or with `Ctrl+C`)

The Python version uses the `text-davinci-003` engine. It was made in the first version of the repo, and since abandoned (at least for now)

## Support

If you like this code or use it in some useful way, consider buying me a coffee: https://www.buymeacoffee.com/unconv
