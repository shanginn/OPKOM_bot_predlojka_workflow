<?php

declare(strict_types=1);

namespace Worker\Tests\Functional;

use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Testing\ActivityMocker;
use Worker\Contracts\PostWorkflowInterface;
use Worker\Tests\TestCase;
use Worker\Workflows\PostWorkflow;

class PostWorkflowTest extends TestCase
{
    private WorkflowClient $workflowClient;
    private ActivityMocker $activityMocks;

    protected function setUp(): void
    {
        $this->workflowClient = new WorkflowClient(ServiceClient::create('localhost:7233'));
        $this->activityMocks = new ActivityMocker();

        parent::setUp();
    }

    public function testBaseWorkflow(): void
    {
        $this->activityMocks->expectCompletion(
            'Telegram.sendToMainChat',
            null,
        );

        $this->activityMocks->expectCompletion(
            'Telegram.removeKeyboard',
            null,
        );

        $workflow = $this->workflowClient->newWorkflowStub(PostWorkflow::class);

        $run = $this->workflowClient->start($workflow);

        self::assertSame(
            [],
            $run->getResult('array')
        );
    }
}