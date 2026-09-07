<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\Http;

use Magento\Framework\HTTP\Client\Curl;
use Ordo\Automation\Model\Http\JsonApiClient;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

class JsonApiClientTest extends TestCase
{
    private Curl&\PHPUnit\Framework\MockObject\MockObject $curl;
    private JsonApiClient $client;

    protected function setUp(): void
    {
        $this->curl = $this->createMock(Curl::class);
        $this->client = new JsonApiClient($this->curl);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testPostJsonSendsPayloadAndExtraHeaders(): void
    {
        $capturedHeaders = [];
        $this->curl->method('addHeader')->willReturnCallback(function (string $name, string $value) use (&$capturedHeaders) {
            $capturedHeaders[$name] = $value;
        });
        $capturedBody = null;
        $this->curl->method('post')->willReturnCallback(function (string $url, string $body) use (&$capturedBody) {
            $capturedBody = $body;
        });
        $this->curl->method('getStatus')->willReturn(200);
        $this->curl->method('getBody')->willReturn('{"ok":true}');

        $result = $this->client->postJson(
            'https://api.example.com/endpoint',
            ['foo' => 'bar'],
            ['Authorization' => 'Bearer token123'],
            'Example API'
        );

        self::assertSame('application/json', $capturedHeaders['Content-Type']);
        self::assertSame('Bearer token123', $capturedHeaders['Authorization']);
        self::assertSame('{"foo":"bar"}', $capturedBody);
        self::assertSame(['ok' => true], $result);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testPostJsonThrowsWithServiceNameOnNonSuccessStatus(): void
    {
        $this->curl->method('getStatus')->willReturn(500);
        $this->curl->method('getBody')->willReturn('server error');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/^Example API request to .* failed \(HTTP 500\): server error$/');

        $this->client->postJson('https://api.example.com/endpoint', [], [], 'Example API');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testPostJsonReturnsEmptyArrayForNonJsonResponse(): void
    {
        $this->curl->method('getStatus')->willReturn(200);
        $this->curl->method('getBody')->willReturn('not json');

        self::assertSame([], $this->client->postJson('https://api.example.com/endpoint', [], [], 'Example API'));
    }
}
