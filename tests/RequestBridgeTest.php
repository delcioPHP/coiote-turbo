<?php

namespace Cabanga\CoioteTurbo;

use Cabanga\CoioteTurbo\Core\RequestBridge;
use Illuminate\Http\Request as LaravelRequest;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Swoole\Http\Request as SwooleRequest;


/**
 * @covers \TurboEngine\Core\RequestBridge
 *
 */
class RequestBridgeTest extends TestCase
{
    // Clean up Mockery after each test.
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Creates a mock of the SwooleRequest for testing purposes.
     */
    private function createSwooleRequestMock(): MockInterface
    {
        // SwooleRequest is a final class with a private constructor,
        // making it hard to mock. We use this trick to create a mockable instance.
        return Mockery::mock(SwooleRequest::class);
    }

    public function test_it_converts_a_basic_get_request(): void
    {
        // Prepare a mock Swoole GET request.
        $swooleRequest = $this->createSwooleRequestMock();
        $swooleRequest->server = [
            'request_method' => 'GET',
            'request_uri' => '/users?id=123',
            'path_info' => '/users',
        ];
        $swooleRequest->header = ['host' => 'localhost'];
        $swooleRequest->get = ['id' => '123'];

        // Mock the rawContent method to avoid errors.
        $swooleRequest->shouldReceive('rawContent')->andReturn('');

        // Perform the conversion.
        $laravelRequest = RequestBridge::convert($swooleRequest);

        // Verify the resulting Laravel request is correct.
        $this->assertInstanceOf(LaravelRequest::class, $laravelRequest);
        $this->assertEquals('GET', $laravelRequest->getMethod());
        $this->assertEquals('/users', $laravelRequest->getPathInfo());
        $this->assertEquals('123', $laravelRequest->query('id'));
    }

    public function test_it_converts_a_post_request_with_json_body(): void
    {
        // Prepare a mock Swoole POST request with a JSON body.
        $swooleRequest = $this->createSwooleRequestMock();
        $jsonPayload = json_encode(['name' => 'Coiote Turbo', 'status' => 'active']);

        $swooleRequest->server = [
            'request_method' => 'POST',
            'request_uri' => '/projects',
        ];
        $swooleRequest->header = [
            'host' => 'localhost',
            'content-type' => 'application/json',
            'content-length' => strlen($jsonPayload),
        ];
        $swooleRequest->post = []; // POST data is in rawContent for JSON requests
        $swooleRequest->shouldReceive('rawContent')->andReturn($jsonPayload);

        // Perform the conversion.
        $laravelRequest = RequestBridge::convert($swooleRequest);
        $this->assertTrue($laravelRequest->isJson());
        // Verify the content and JSON data.
        $this->assertEquals('POST', $laravelRequest->getMethod());
        $this->assertEquals($jsonPayload, $laravelRequest->getContent());
        $this->assertEquals('Coiote Turbo', $laravelRequest->json('name'));
    }

    public function test_it_correctly_maps_headers(): void
    {
        // Prepare a mock request with various headers.
        $swooleRequest = $this->createSwooleRequestMock();
        $swooleRequest->server = ['request_method' => 'GET', 'request_uri' => '/'];
        $swooleRequest->header = [
            'host' => 'turbo.test',
            'x-request-id' => 'abc-123',
            'accept' => 'application/json',
        ];
        $swooleRequest->shouldReceive('rawContent')->andReturn('');

        // Perform the conversion.
        $laravelRequest = RequestBridge::convert($swooleRequest);

        // Verify that headers are correctly mapped.
        // Laravel prefixes headers with HTTP_ and converts to uppercase.
        $this->assertTrue($laravelRequest->headers->has('x-request-id'));
        $this->assertEquals('abc-123', $laravelRequest->header('x-request-id'));
        $this->assertEquals('turbo.test', $laravelRequest->header('host'));
        $this->assertTrue($laravelRequest->acceptsJson());
    }
}