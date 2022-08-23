<?php

declare(strict_types=1);

namespace Worker\Workflows;

use Carbon\CarbonInterval;
use Generator;
use Temporal\Activity\ActivityOptions;
use Temporal\DataConverter\Type;
use Temporal\Workflow;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;
use Worker\Contracts\Enums\VoteType;
use Worker\Contracts\PostWorkflowInterface;
use Worker\Contracts\Config;
use Worker\Services\TelegramService;

#[WorkflowInterface]
class PostWorkflow
{
    private const KEY_PREFIX = 'VOTE';
    /**
     * @var TelegramService
     */
    private $telegram;

    private Config $config;

    private array $votes = [];

    public function __construct()
    {
        $this->telegram = Workflow::newActivityStub(
            TelegramService::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout(CarbonInterval::minute())
        );
    }

    #[WorkflowMethod]
    #[ReturnType(Type::TYPE_ARRAY)]
    public function create(Config $config): Generator
    {
        $this->config = $config;

        yield Workflow::awaitWithTimeout(
            CarbonInterval::hours(24),
            fn () => $this->worth()
        );

        if ($this->worth()) {
            yield $this->telegram->sendToMainChat($this->config->messageId);
        }

        return $this->countVotes();
    }

    #[Workflow\SignalMethod]
    public function vote(int $voterId, string $voteType): void
    {
        $voteType = VoteType::from($voteType);

        $currentVote = $this->getVote($voterId);

        if ($currentVote !== null && $currentVote === $voteType) {
            $this->unsetVote($voterId);
        } else {
            $this->setVote($voterId, $voteType);
        }
    }

    private function getVote(int $voterId): ?VoteType
    {
        return $this->votes[self::KEY_PREFIX . $voterId] ?? null;
    }

    private function setVote(int $voterId, VoteType $voteType): void
    {
        $this->votes[self::KEY_PREFIX . $voterId] = $voteType;
    }

    private function unsetVote(int $voterId): void
    {
        unset($this->votes[self::KEY_PREFIX . $voterId]);
    }

    #[Workflow\QueryMethod]
    public function getUpVotes(): int
    {
        return $this->countVotes()[VoteType::UP->name] ?? 0;
    }

    #[Workflow\QueryMethod]
    public function getDownVotes(): int
    {
        return $this->countVotes()[VoteType::DOWN->name] ?? 0;
    }

    #[Workflow\QueryMethod]
    public function countVotes(): array
    {
        return array_reduce($this->votes, function (array $carry, VoteType $voteType) {
            if (!isset($carry[$voteType->name])) {
                $carry[$voteType->name] = 0;
            }

            $carry[$voteType->name]++;

            return $carry;
        }, []);
    }

    private function worth(): bool
    {
        return $this->getUpVotes() - $this->getDownVotes() >= 3;
    }

    #[Workflow\QueryMethod]
    public function getVotes(): array
    {
        return $this->votes;
    }
}
