<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Controller\Track;

use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Ordo\Automation\Controller\Track\PushServiceWorker;
use Ordo\Automation\Test\Unit\Controller\AbstractFrontendActionTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

class PushServiceWorkerTest extends AbstractFrontendActionTestCase
{
    private RawFactory $resultRawFactory;
    private Filesystem $filesystem;
    private ModuleDirReader $moduleDirReader;
    private Raw $rawResult;
    private ReadInterface $directory;

    /** @var array<string, string> */
    private array $capturedHeaders;

    protected function setUp(): void
    {
        $this->resultRawFactory = $this->createStub(RawFactory::class);
        $this->filesystem = $this->createMock(Filesystem::class);
        $this->moduleDirReader = $this->createStub(ModuleDirReader::class);
        $this->moduleDirReader->method('getModuleDir')->willReturn('/app/code/Ordo/Automation/view');

        $this->directory = $this->createStub(ReadInterface::class);
        $this->filesystem->method('getDirectoryReadByPath')->willReturn($this->directory);

        $this->capturedHeaders = [];
        $this->rawResult = $this->createMock(Raw::class);
        $this->rawResult->method('setHeader')->willReturnCallback(function (string $name, string $value) {
            $this->capturedHeaders[$name] = $value;
            return $this->rawResult;
        });
        $this->resultRawFactory->method('create')->willReturn($this->rawResult);
    }

    private function makeController(): PushServiceWorker
    {
        return new PushServiceWorker($this->makeContext(), $this->resultRawFactory, $this->filesystem, $this->moduleDirReader);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteServesFileContentsWithServiceWorkerAllowedHeader(): void
    {
        $this->directory->method('isExist')->willReturn(true);
        $this->directory->method('readFile')->willReturn('self.addEventListener("push", function(){});');

        $this->rawResult->expects(self::once())->method('setContents')
            ->with('self.addEventListener("push", function(){});');

        $this->makeController()->execute();

        self::assertSame('application/javascript; charset=UTF-8', $this->capturedHeaders['Content-Type']);
        self::assertSame('/', $this->capturedHeaders['Service-Worker-Allowed']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteReturns404WhenFileMissing(): void
    {
        $this->directory->method('isExist')->willReturn(false);

        $this->rawResult->expects(self::once())->method('setHttpResponseCode')->with(404);
        $this->rawResult->expects(self::never())->method('setContents');

        $this->makeController()->execute();
    }
}
