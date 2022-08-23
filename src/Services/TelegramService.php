<?php

declare(strict_types=1);

namespace Worker\Services;

use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Longman\TelegramBot\Request;

#[ActivityInterface(prefix: 'Telegram.')]
class TelegramService
{
    private string $mainChatId;
    private string $suggestChatId;
    private Api $bot;

    public function __construct()
    {
        $this->mainChatId = $_ENV['MAIN_CHAT_ID'];
        $this->suggestChatId = $_ENV['SUGGEST_CHAT_ID'];
        $this->bot = new Api($_ENV['TELEGRAM_BOT_TOKEN']);
    }

    #[ActivityMethod(name: 'sendToMainChat')]
    public function sendToMainChat(int $messageId): void
    {
        $this->bot->copyMessage([
            'chat_id' => $this->mainChatId,
            'from_chat_id' => $this->suggestChatId,
            'message_id' => $messageId,
        ]);
    }

    #[ActivityMethod(name: 'removeLikesButtons')]
    public function removeLikesButtons(
        string $messageId,
        int $upVotes,
        int $downVotes,
        int $votesToWorth
    ): void {
        $keyboardInline = (new InlineKeyboard([]))
            ->addRow(new InlineKeyboardButton([
                'text' => sprintf(
                    'Голосование окончено. Результат: %d/%d',
                    $upVotes - $downVotes,
                    $votesToWorth,
                ),
                'callback_data' => 'none',
            ]));

        $this->updateKeyboardMarkup($messageId, $keyboardInline);
    }

    #[ActivityMethod(name: 'updateKeyboardCounter')]
    public function updateKeyboard(
        string $messageId,
        int $upVotes,
        int $downVotes,
        int $votesToWorth,
        int $hoursLeft
    ): void {
        $keyboardInline = (new InlineKeyboard([]))
            ->addRow(new InlineKeyboardButton([
                'text' => sprintf(
                    '%d/%d. Осталось часов: %d',
                    $upVotes - $downVotes,
                    $votesToWorth,
                    $hoursLeft
                ),
                'callback_data' => 'none',
            ]))
            ->addRow(
                new InlineKeyboardButton([
                    'text' => sprintf('👍 %d', $upVotes ?? 0),
                    'callback_data' => '👍',
                ]),
                new InlineKeyboardButton([
                    'text' => sprintf('👎 %d', $downVotes ?? 0),
                    'callback_data' => '👎',
                ])
            );

        $this->updateKeyboardMarkup($messageId, $keyboardInline);
    }

    private function updateKeyboardMarkup(string $messageId, InlineKeyboard $keyboard): void
    {
        try {
            $this->bot->editMessageReplyMarkup([
                'chat_id' => $this->suggestChatId,
                'message_id' => $messageId,
                'reply_markup' => $keyboard,
            ]);
        } catch (TelegramSDKException $e) {
            // if exception message contains text "message is not modified" ignore it
            if (strpos($e->getMessage(), 'message is not modified') === false) {
                throw $e;
            }
        }
    }
}
