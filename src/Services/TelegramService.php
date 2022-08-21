<?php

declare(strict_types=1);

namespace Worker\Services;

use Telegram\Bot\Api;
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
    public function sendToMainChat(int $messageId)
    {
        $this->bot->copyMessage([
            'chat_id' => $this->mainChatId,
            'from_chat_id' => $this->suggestChatId,
            'message_id' => $messageId,
        ]);
    }
}
