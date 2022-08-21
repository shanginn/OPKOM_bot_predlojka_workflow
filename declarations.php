<?php

declare(strict_types=1);

return [
    'orders' => [
        'workflows' => [
            \Worker\Workflows\PostWorkflow::class,
        ],
        'activities' => [
            \Worker\Services\TelegramService::class,
        ],
    ],
];
