# ChatWTF

This is a ChatGPT-like chatbot that uses the ChatGPT API. It was created for my YouTube channel. You can find the playlist of videos [here](https://www.youtube.com/watch?v=ru5m-BKDn6E&list=PLz8w2NTEwxvqH7yCAp6PAL0dKeiVU7uv4).

## Quick Start

1. Clone the repository
2. Add your OpenAI API key to `settings.php` (see `settings.sample.php`)
3. Start a server

```console
$ php -S localhost:8080
```

4. Go to http://localhost:8080

## Docker

```console
$ sudo docker build -t chatwtf .
$ sudo docker run -p 8080:80 chatwtf
```

Note: If you get `caught SIGWINCH, shutting down gracefully`, add the `-d` flag to run it in the background.

## Database

The chatbot uses PHP sessions to store the conversations by default. You can also use an SQL database. There is a SQLite dump and a MySQL dump in the `db` folder. You can install the SQLite version by running the `install_sqlite.php` script.

Database config has to be put into `settings.php` (see `settings.sample.php`). You need to also change `storage_type` to `sql` in the settings in order to use a database.

## API key

You will need an API key from OpenAI to use the code. The API key must be added to the `settings.sample.php` file, which you will need to rename to `settings.php`.

## CodeInterpreter

By default (when enabled from `settings.php`), CodeInterpreter mode **runs Python code directly on your machine** but it asks for confirmation first. To enable a sandbox environment with Docker, change `code_interpreter` > `sandbox` > `enabled` to `true` in `settings.php` and set `code_interpreter` > `sandbox` > `container` to the name of the Python sandbox Docker container.

You can create such a sandbox container by running:

```shell
$ sudo docker build -t chatwtf-sandbox ./sandbox
```

Note that the sandbox doesn't work (and might not be needed) if you're already running the project inside a Docker container.

## Modify to your liking

You can change the system message in the settings to make the chatbot do what you want.

## Support

If you like this code or use it in some useful way, consider buying me a coffee: https://www.buymeacoffee.com/unconv
