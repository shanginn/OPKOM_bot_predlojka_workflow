<?php

declare(strict_types=1);

namespace Worker\Tests\Functional;

use Faker\Factory;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Testing\ActivityMocker;
use Worker\Contracts\Config;
use Worker\Contracts\PostWorkflowInterface;
use Worker\Tests\TestCase;
use Worker\Workflows\PostWorkflow;

class PostWorkflowTest extends TestCase
{
    private WorkflowClient $workflowClient;
    private ActivityMocker $activityMocks;
    private \Faker\Generator $faker;

    protected function setUp(): void
    {
        $this->workflowClient = new WorkflowClient(ServiceClient::create('localhost:7233'));
        $this->activityMocks = new ActivityMocker();
        $this->faker = Factory::create();

        parent::setUp();
    }

    public function testBaseWorkflow(): void
    {
//        $this->activityMocks->expectCompletion(
//            'Telegram.sendToMainChat',
//            null,
//        );

        $this->activityMocks->expectCompletion(
            'Telegram.removeLikesButtons',
            null,
        );

        $this->activityMocks->expectCompletion(
            'Telegram.updateKeyboardCounterWithMinutes',
            null,
        );

        $workflow = $this->workflowClient->newWorkflowStub(PostWorkflow::class);

        $messageId = $this->faker->randomNumber();
        $authorId = $this->faker->randomNumber();

        $run = $this->workflowClient->start(
            $workflow,
            new Config(
                messageId: $messageId,
                authorId: $authorId,
            ),
        );

        self::assertSame(
            [],
            $run->getResult('array')
        );
    }
}