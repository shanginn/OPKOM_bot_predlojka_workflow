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
    private const HOURS_TO_VOTE = 24;

    const VOTES_TO_WORTH = 3;

    /**
     * @var TelegramService
     */
    private $telegram;

    private Config $config;

    private int $hoursLeft = self::HOURS_TO_VOTE;
    private int $minutesLeft = self::HOURS_TO_VOTE * 60;

    /**
     * @var array<string, VoteType>
     */
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

        $newAlgoVersion = yield Workflow::getVersion(
            'newAlgo',
            Workflow::DEFAULT_VERSION,
            1
        );

        $countdownUpdaterPromise = Workflow::async(function () {
            yield $this->updateKeyboard();

            while (true) {
                yield Workflow::timer(CarbonInterval::hour());
                $this->hoursLeft--;

                yield $this->updateKeyboard();

                if ($this->hoursLeft === 0) {
                    break;
                }
            }
        });

        yield Workflow::awaitWithTimeout(
            CarbonInterval::hours(self::HOURS_TO_VOTE),
            fn() => $this->worth()
        );

        if ($this->worth()) {
            yield $this->telegram->sendToMainChat($this->config->messageId);
        }

        yield $this->removeLikesButtons();

        $countdownUpdaterPromise->cancel();

        return $this->countVotes();
    }

    #[Workflow\SignalMethod]
    public function vote(int $voterId, string $voteType): Generator
    {
        $voteType = VoteType::from($voteType);

        $currentVote = $this->getVote($voterId);

        if ($currentVote !== null && $currentVote === $voteType) {
            $this->unsetVote($voterId);
        } else {
            $this->setVote($voterId, $voteType);
        }

        $newAlgoVersion = yield Workflow::getVersion(
            'newAlgo',
            Workflow::DEFAULT_VERSION,
            1
        );

        if ($newAlgoVersion === Workflow::DEFAULT_VERSION) {
            yield $this->updateKeyboard();
        } else {
            yield $this->updateKeyboardWithMinutes();
        }
    }

    #[Workflow\SignalMethod]
    public function updateKeyboard(): Generator
    {
        yield $this->telegram->updateKeyboardCounter(
            (string) $this->config->messageId,
            $this->getUpVotes(),
            $this->getDownVotes(),
            self::VOTES_TO_WORTH,
            $this->hoursLeft
        );
    }

    public function updateKeyboardWithMinutes(): Generator
    {
        yield $this->telegram->updateKeyboardWithMinutes(
            (string) $this->config->messageId,
            $this->getUpVotes(),
            $this->getDownVotes(),
            self::VOTES_TO_WORTH,
            $this->minutesLeft
        );
    }

    #[Workflow\SignalMethod]
    public function removeLikesButtons(): Generator
    {
        yield $this->telegram->removeLikesButtons(
            (string) $this->config->messageId,
            $this->getUpVotes(),
            $this->getDownVotes(),
            self::VOTES_TO_WORTH,
        );
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
        return $this->getUpVotes() - $this->getDownVotes() >= self::VOTES_TO_WORTH;
    }

    private function timeToCheck(): bool
    {
        return $this->minutesLeft % 60 === 0;
    }

    #[Workflow\QueryMethod]
    public function getVotes(): array
    {
        return $this->votes;
    }
}
