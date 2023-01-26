#!/usr/bin/env python3

import openai

# set api key file
openai.api_key_path = "api_key.txt"

# set default prompt
default_prompt = "Act as an AI mentor for a programmer by answering the questions provided. If the question is related to a piece of code, write the code and explain what it does and how it works in simple terms. Format the response in Markdown format so that the code can be distinguised from it easily. Please also explain the steps involved, don't only tell the code to use. Every response must have more than just code: at least one sentence about the code. If you're asked for your identity, say that your name is the magnificent ChatWTF.\n\n"

# initialize example question and answer
old_prompts = "Question:\n'How do you write a hello world script in PHP?'\n\nAnswer:\nIn PHP, you can write a hello world script with the following code:\n\n```\n<?php\necho 'Hello world';\n?>\n```\n\nYou need to put this code into a file with the .php extension and then run it with PHP or with a web server.\n\nQuestion:\n'Can you use the print function instead?'\n\nAnswer:\nCertainly! Here's how you would use the `print` function insted:\n\n```\n<?php\nprint('Hello world');\n?>\n```\n\n"

# initialize variables
old_response = None
old_question = None
please_use_above = ""

while(True):
    # ask for a question
    print("What is your question? ", end="")
    question = input()

    if question == "exit":
        exit()

    # append old question to prompt
    if old_response != None:
        old_prompts = old_prompts + "Question:\n" + old_question + "\n\nAnswer:\n\n" + old_response + "\n\n"
        please_use_above = ". Please use the questions and answers above as context for the answer."

    full_prompt = old_prompts + "Question:\n" + question + please_use_above + "\n\nAnswer:\n\n"

    # send request to openai
    response = openai.Completion.create(
        engine="text-davinci-003",
        prompt=full_prompt,
        temperature=0.9,
        max_tokens=1000,
        top_p=1,
        frequency_penalty=0,
        presence_penalty=0,
    )

    # format response
    response_text = response.choices[0].text.replace("\\n", "\n")

    # print response
    print("\n" + response_text + "\n")

    # save old question and response
    old_question = question
    old_response = response_text