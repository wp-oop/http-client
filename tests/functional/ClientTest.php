<?php

namespace WpOop\HttpClient\Test\Func;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use TypeError;
use WP_Error;
use WpOop\HttpClient\Client as Subject;

use WpOop\HttpClient\ClientException;
use WpOop\HttpClient\Test\WpTestCase;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * @psalm-import-type WpRequestOptions from \WpOop\HttpClient\Client
 */
class ClientTest extends WpTestCase
{
    public function testSendRequest()
    {
        {
            $method = 'POST';
            $url = sprintf('http://somesite.com/%1$s', uniqid('path'));
            $operationId = uniqid('opid');
            $headers = $this->getDummyHeaders($operationId);
            $body = uniqid('body');
            $protocolVersion = (rand(1, 100) % 2) ? '1.0' : '1.1';
            $wpOptions = [
                'timeout' => rand(1, 60),
                'redirection' => rand(3, 5),
            ];
            $responseFactory = $this->createResponseFactory();
            $request = $this->createRequest($method, $url, $headers, $body, $protocolVersion);
            $responseCode = rand(200, 299);
            $responseMessage = uniqid('response-message');
            $responseHeaders = $this->getDummyHeaders($operationId);
            $responseBody = uniqid('response');
            $wpResponse = [
                'headers' => $responseHeaders,
                'body' => $responseBody,
                'response' => [
                    'code' => $responseCode,
                    'message' => $responseMessage,
                ],
                'cookies' => [],
                'filename' => '',
            ];
            $subject = $this->createSubject($wpOptions, $responseFactory);
        }

        {
            expect('wp_remote_request')
                ->times(1)
                ->with(
                    $url,
                    array_merge($wpOptions, [
                        'method' => $method,
                        'httpversion' => $protocolVersion,
                        'headers' => $headers,
                        'body' => $body,
                    ])
                )
                ->andReturn($wpResponse);

            expect('wp_remote_retrieve_response_code')
                ->times(1)
                ->with(
                    $wpResponse
                )
                ->andReturn($responseCode);

            expect('wp_remote_retrieve_response_message')
                ->times(1)
                ->with(
                    $wpResponse
                )
                ->andReturn($responseMessage);

            expect('wp_remote_retrieve_headers')->mockeryExpectation()
                ->times(1)
                ->with(
                    $wpResponse
                )
                ->andReturn($responseHeaders);

            expect('wp_remote_retrieve_body')->mockeryExpectation()
                ->times(1)
                ->with(
                    $wpResponse
                )
                ->andReturn($responseBody);


            $response = $subject->sendRequest($request);
            $this->assertInstanceOf(ResponseInterface::class, $response);
        }
    }

    public function testErrorSending()
    {
        {
            $method = 'POST';
            $url = sprintf('http://somesite.com/%1$s', uniqid('path'));
            $operationId = uniqid('opid');
            $headers = $this->getDummyHeaders($operationId);
            $body = uniqid('body');
            $protocolVersion = (rand(1, 100) % 2) ? '1.0' : '1.1';
            $wpOptions = [
                'timeout' => rand(1, 60),
                'redirection' => rand(3, 5),
            ];
            $responseFactory = $this->createResponseFactory();
            $request = $this->createRequest($method, $url, $headers, $body, $protocolVersion);
            $error = $this->createWpError('Some error during WP request', 'http_request_failed');
            $subject = $this->createSubject($wpOptions, $responseFactory);
        }

        {
            expect('wp_remote_request')
                ->times(1)
                ->with(
                    $url,
                    array_merge($wpOptions, [
                        'method' => $method,
                        'httpversion' => $protocolVersion,
                        'headers' => $headers,
                        'body' => $body,
                    ])
                )
                ->andReturn($error);

            $this->expectException(ClientException::class);
            $subject->sendRequest($request);
        }
    }

    public function testErrorReassembling()
    {
        {
            $method = 'POST';
            $url = sprintf('http://somesite.com/%1$s', uniqid('path'));
            $operationId = uniqid('opid');
            $headers = $this->getDummyHeaders($operationId);
            $body = uniqid('body');
            $protocolVersion = (rand(1, 100) % 2) ? '1.0' : '1.1';
            $wpOptions = [
                'timeout' => rand(1, 60),
                'redirection' => rand(3, 5),
            ];
            $responseFactory = $this->createResponseFactory();
            $request = $this->createRequest($method, $url, $headers, $body, $protocolVersion);
            $responseCode = uniqid();
            $responseMessage = uniqid('response-message');
            $responseHeaders = $this->getDummyHeaders($operationId);
            $responseBody = uniqid('response');
            $wpResponse = [
                'headers' => $responseHeaders,
                'body' => $responseBody,
                'response' => [
                    'code' => $responseCode,
                    'message' => $responseMessage,
                ],
                'cookies' => [],
                'filename' => '',
            ];
            $subject = $this->createSubject($wpOptions, $responseFactory);
        }

        {
            expect('wp_remote_request')
                ->times(1)
                ->with(
                    $url,
                    array_merge($wpOptions, [
                        'method' => $method,
                        'httpversion' => $protocolVersion,
                        'headers' => $headers,
                        'body' => $body,
                    ])
                )
                ->andReturn($wpResponse);

            expect('wp_remote_retrieve_response_code')
                ->times(1)
                ->with(
                    $wpResponse
                )
                ->andReturn($responseCode);

            $this->expectException(ClientException::class);
            $subject->sendRequest($request);
        }
    }

    /**
     * @param WpRequestOptions $wpOptions The non-request-specific options to pass to WordPress.
     * @param ResponseFactoryInterface $responseFactory
     *
     * @return Subject&MockObject
     */
    protected function createSubject(
        array $wpOptions,
        ResponseFactoryInterface $responseFactory
    ): Subject {
        $mock = $this->getMockBuilder(Subject::class)
            ->setConstructorArgs([$wpOptions, $responseFactory])
            ->enableProxyingToOriginalMethods()
            ->enableOriginalConstructor()
            ->getMock();

        return $mock;
    }

    /**
     * @return ResponseFactoryInterface&MockObject
     */
    protected function createResponseFactory(): ResponseFactoryInterface
    {
        $mock = $this->getMockBuilder(Psr17Factory::class)
            ->enableProxyingToOriginalMethods()
            ->enableOriginalConstructor()
            ->getMock();

        return $mock;
    }

    /**
     * @param string $method HTTP method
     * @param string|UriInterface $uri URI
     * @param array $headers Request headers
     * @param string|StreamInterface|null $body Request body
     * @param string $version Protocol version
     */
    protected function createRequest(
        string $method,
        $uri,
        array $headers = [],
        $body = null,
        string $version = '1.1'
    ): RequestInterface {
        return new Request($method, $uri, $headers, $body, $version);
    }

    /**
     * @return WP_Error
     */
    protected function createWpError(string $message, string $code = '', $data = ''): WP_Error
    {
        return new WP_Error($code, $message, $data);
    }

    /**
     * @return array<string, scalar|array<scalar>> A map of header names to header values.
     */
    protected function getDummyHeaders(string $testValue): array
    {
        return [
            'X-Test-Value' => $testValue,
            'Host' => 'somesite.com',
        ];
    }

    /**
     * @param RequestInterface $request
     * @return array<string, scalar|array<scalar>>
     */
    protected function getRequestHeaders(RequestInterface $request): array
    {
        $headers = [];
        foreach ($request->getHeaders() as $headerName => $header) {
            $headers[$headerName] = $request->getHeaderLine($headerName);
        }

        return $headers;
    }
}
