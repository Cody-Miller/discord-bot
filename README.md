# mrboterson

## Intro

This is a silly/simple discord bot with the following commands:

```
General -
    !gpt -t [tokens(default: 10)] -m [model(default: gpt-3.5-turbo)] -p [prompt]
    !gptsetkey [key] *default uses my key but the free trial is over*
    !gptgetkey
    !xd
Dice - 
    !d20 ?[###]
    !d12 ?[###]
    !d10 ?[###]
    !d8 ?[###]
    !d6 ?[###]
    !d4 ?[###]
```

## Setup

- Have composer installed
- Have PHP8+ installed
- Have a connection to the internet (no web server required)
- Have a discord bot set up with a token
- Have a chat GPT API key (if you want that to work) *also note that anyone will be using this key so maybe restrict access to the bot or the discord it's active in*
- Run `cp .env.example .env`
- Update `.env` to hold the Discord token and the GPT API key
- Run `php mrboterson.php`
	- Run `nohup php mrboterson.php &` if you want to disown the process and keep it running after closing the terminal