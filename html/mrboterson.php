<?php

ini_set("memory_limit", "-1");

require_once __DIR__.'/../vendor/autoload.php';

use Discord\Discord;
use Discord\Parts\Channel\Message;
use Discord\WebSockets\Intents;
use Discord\WebSockets\Event;
use Dotenv\Dotenv;

$dotenv = Dotenv::create('/var/www/');
$dotenv->load();

$discord = new Discord([
    'token' => getenv('DISCORD_BOT_TOKEN')
    //'intents' => Intents::getDefaultIntents()
]);

$discord->on('ready', function (Discord $discord) {
    echo "Bot is ready!", PHP_EOL;

    // Listen for messages.
    $discord->on(Event::MESSAGE_CREATE, function (Message $message, Discord $discord) {
            $content = $message->content;
            if (strpos($content, '!') === false || $message->author->username == 'mrboterson') {
                return;
            }
            $content = strtolower($content);
            if ($content === '!help' || $content === '!man' || $content === '!h') {
                $message->reply("List of commands:
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
Future -
    !dndViewPF
    !dndSetStr
    !dndSetInt
    ...
```"
                );
                return;
            }
            if ($content === '!xd') {
                $message->reply('This bot took me wayyy longer then it should have.');
                return;
            }
            if (str_starts_with($content, '!gpt ')) {
                $contentParts = explode(' ', $content, 2);
                $commandParts = parseCommand($contentParts[1], ['-t', '-p', '-m']);
                if(!array_key_exists('-p', $commandParts)){
                    $message->reply('-p required for GPT command');
                    unset($contentParts, $commandParts, $client, $response, $responseJson, $token, $model, $prompt);
                    return;  
                } else {
                    $prompt = $commandParts['-p'];
                }
                $token = 10;
                if(array_key_exists('-t', $commandParts)){
                    $token = $commandParts['-t'];
                }
                $model = 'gpt-3.5-turbo';
                if(array_key_exists('-m', $commandParts)){
                    $model = $commandParts['-m'];
                }
                try {
                    $client = new \GuzzleHttp\Client();
                    $apiKey = getOpenAiAPIKey();
                    $response = $client->post(
                        'https://api.openai.com/v1/completions',
                        [
                            'headers' => [
                                'Content-Type' => 'application/json',
                                'Authorization' => "Bearer {$apiKey}"
                            ],
                            'json' => [
                                'model' => $model,
                                'prompt' => $prompt,
                                'temperature' => 0,
                                'max_tokens' => $token
                            ]
                        ]
                    );
                    $responseJson = json_decode((string)$response->getBody());
                    $message->reply("{$responseJson?->choices[0]?->text} - {$responseJson?->model}" );
                } catch (Exception $e) {
                    $message->reply("GPT request failed " . $e->getMessage());
                }
                unset($contentParts, $commandParts, $client, $response, $responseJson, $token, $model, $prompt);
                return;
            }
            if ($content === '!gptgetkey') {
                $message->reply('Key: ' . getOpenAiAPIKey(false));
                return;
            }
            if (str_starts_with($content, '!gptsetkey')) {
                $contentParts = explode(' ', $content, 2);
                setOpenAiAPIKey($contentParts[1]);
                $message->reply('GPT key has been updated...');
                return;
            }
            if (
                str_starts_with($content, '!d20')
                || str_starts_with($content, '!d10')
                || str_starts_with($content, '!d12')
                || str_starts_with($content, '!d8')
                || str_starts_with($content, '!d6')
                || str_starts_with($content, '!d4')
            ) {
                // If we have a number following the command we roll that many dice
                if (str_contains($content, ' ')) {
                    $contentParts = explode(' ', $content, 2);
                    $dice = str_replace('!d', '', $contentParts[0]);
                    $numberOfDice = $contentParts[1];
                    if (
                        is_numeric($dice)
                        && is_numeric($contentParts[1])
                        && $contentParts[1] > 0
                        && $contentParts[1] <= 100
                    ) {
                       $output = 'ROLLS: ';
                       $total = 0;
                       for ($count=0; $count < $numberOfDice; $count++) { 
                            $rollResult = rand(1, (int)$dice);
                            $output .= "{$rollResult} ";
                            $total += $rollResult;
                       }
                       $message->reply("{$output} TOTAL: {$total}");
                       unset($dice, $numberOfDice, $output, $total, $contentParts, $count);
                    } else {
                        $message->reply('!d# commands only accept input as number from 1-100');
                    }
                } elseif (
                    $content == '!d20'
                    || $content == '!d10'
                    || $content == '!d12'
                    || $content == '!d8'
                    || $content == '!d6'
                    || $content == '!d4'
                ) {
                    $rollResult = rand(1, (int)str_replace('!d', '', $content));
                    $message->reply("ROLL: {$rollResult}");
                    unset($rollResult);
                } else {
                    $message->reply('Invalid dice found, currently only support for standard dice');
                }
                return;
            }
    });
});

$discord->run();

/**
 * Receives in a command and it's related flags and converts them to an array with the
 *  key as the flag and the part of the command between the flags as the content
 * 
 * @param string $command - the command after the primary directive is found
 * @param array $flags - an array of all the flags this can be parsed out
 * @return array
 */
function parseCommand(string $command, array $flags): array
{
    $commandParts = [];
    foreach ($flags as $startFlag) {
        // If the string contains a flag get the end of string till the next flag
        if (str_contains($command, $startFlag)) {
            // Get the string after the flag
            $statPos = strpos($command, $startFlag);
            $command = trim(str_replace($startFlag, '', $command));
            $cutoff = strlen($command);
            foreach ($flags as $endFlag) {
                // If the string contains an end flag and it comes after the start flag
                if (
                    str_contains($command, $endFlag)
                    && strpos($command, $endFlag) > $statPos
                ) {
                    $cutoff = min($cutoff, strpos($command, $endFlag));
                }
            }
            $commandParts[$startFlag] = trim(substr($command, $statPos, $cutoff));
            $command = trim(str_replace($commandParts[$startFlag], '', $command));
        }
    }
    return $commandParts;
}

function setOpenAiAPIKey($key)
{
    // If we have a file we want to first clear it
    if (file_exists("/var/www/openaiapi.txt")) {
        file_put_contents("/var/www/openaiapi.txt", "");
    }

    // If we have new key contents we want to write it (and make the file if it did not exists)
    if (!empty($key)) {
        $keyfile = fopen("/var/www/openaiapi.txt", "w");
        fwrite($keyfile, $key);
        fclose($keyfile);
    }
}

function getOpenAiAPIKey($full = true)
{
    // Default to our env
    $key = getenv('OPENAI_API_KEY');
    // If we have a key file
    if (file_exists("/var/www/openaiapi.txt") && filesize("/var/www/openaiapi.txt") > 0) {
        // Read from it
        $keyfile = fopen("/var/www/openaiapi.txt", "r");
        $keyfileContents = fread($keyfile, filesize("/var/www/openaiapi.txt"));
        // If it has contents set our key to it
        fclose($keyfile);
        if (!empty($keyfileContents)) {
            $key = $keyfileContents;
        }
    }
    // If we are getting a full key for use with api calls pass the whole key back if not don't
    if ($full) {
        return $key;
    } else {
        return substr($key, 0, 10) . '...';
    }
}
