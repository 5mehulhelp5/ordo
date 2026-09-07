<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Controller\Track;

use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Ordo\Automation\Controller\Track\UnregisterPushSubscription;
use Ordo\Automation\Model\Push\PushSubscriptionManager;
use Ordo\Automation\Test\Unit\Controller\AbstractFrontendActionTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

class UnregisterPushSubscriptionTest extends AbstractFrontendActionTestCase
{
    private JsonFactory $resultJsonFactory;
    private PushSubscriptionManager $pushSubscriptionManager;
    private Json $jsonResult;

    protected function setUp(): void
    {
        $this->resultJsonFactory = $this->createStub(JsonFactory::class);
        $this->pushSubscriptionManager = $this->createMock(PushSubscriptionManager::class);

        $this->jsonResult = $this->createMock(Json::class);
        $this->jsonResult->method('setData')->willReturnSelf();
        $this->resultJsonFactory->method('create')->willReturn($this->jsonResult);
    }

    private function makeController(): UnregisterPushSubscription
    {
        return new UnregisterPushSubscription($this->makeContext(), $this->resultJsonFactory, $this->pushSubscriptionManager);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteReturnsInvalidPayloadWhenEndpointMissing(): void
    {
        $controller = $this->makeController();
        $this->request->method('getParam')->willReturnMap([['endpoint', null, '']]);

        $this->jsonResult->expects(self::once())->method('setData')->with(['ok' => false, 'reason' => 'invalid_payload']);
        $this->pushSubscriptionManager->expects(self::never())->method('unregister');

        $controller->execute();
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteUnregistersEndpoint(): void
    {
        $controller = $this->makeController();
        $this->request->method('getParam')->willReturnMap([['endpoint', null, 'https://push.example.com/1']]);

        $this->pushSubscriptionManager->expects(self::once())->method('unregister')->with('https://push.example.com/1');
        $this->jsonResult->expects(self::once())->method('setData')->with(['ok' => true]);

        $controller->execute();
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCreateCsrfValidationExceptionReturnsNull(): void
    {
        $controller = $this->makeController();
        self::assertNull($controller->createCsrfValidationException($this->request));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testValidateForCsrfReturnsTrue(): void
    {
        $controller = $this->makeController();
        self::assertTrue($controller->validateForCsrf($this->request));
    }
}
