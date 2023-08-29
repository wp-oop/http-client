<?php

namespace WpOop\HttpClient\Test\Func;

use bovigo\vfs\vfsStream;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;
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

    public function testSendRequestStreaming()
    {
        {
            $fs = vfsStream::setup('tmprequest');
            $fsBaseDir = $fs->url();
            $streamProxyDir = $fsBaseDir;
            $streamProxyFile = $this->getTempFilePath($streamProxyDir, 'request');
            $method = 'POST';
            $url = sprintf('http://somesite.com/%1$s', uniqid('path'));
            $operationId = uniqid('opid');
            $headers = $this->getDummyHeaders($operationId);
            $body = uniqid('body');
            file_put_contents($streamProxyFile, $body); // Write body contents to proxy file
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
            $wpResponse = [
                'headers' => $responseHeaders,
                'body' => '',
                'response' => [
                    'code' => $responseCode,
                    'message' => $responseMessage,
                ],
                'cookies' => [],
                'filename' => '',
            ];
            $subject = $this->createSubject($wpOptions, $responseFactory, $streamProxyDir);
        }

        {
            // Use streaming proxy file at this location
            $subject->expects($this->exactly(1))
                ->method('getTempFilePath')
                ->will($this->returnCallback(function () use ($streamProxyFile) {
                    return $streamProxyFile;
                }));

            expect('wp_remote_request')
                ->with(
                    $url,
                    $this->equalToCanonicalizing(array_merge($wpOptions, [
                        'method' => $method,
                        'httpversion' => $protocolVersion,
                        'headers' => $headers,
                        'body' => $body,
                        'stream' => true,
                        'filename' => $streamProxyFile,
                    ]))
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

            $response = $subject->sendRequest($request);
            $responseBody = $response->getBody()->getContents();
            $this->assertEquals($body, $responseBody);
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
     * @param bool $isMockStreaming If true, the methods that are used for streaming will be mockable.
     *
     * @return Subject&MockObject
     */
    protected function createSubject(
        array $wpOptions,
        ResponseFactoryInterface $responseFactory,
        bool $isMockStreaming = null
    ): Subject {
        $methods = [];

        if ($isMockStreaming !== null) {
            $methods[] = 'getTempFilePath';
        }

        $mock = $this->getMockBuilder(Subject::class)
            ->onlyMethods($methods)
            ->setConstructorArgs([$wpOptions, $responseFactory, $isMockStreaming])
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

    /**
     * Retrieves a unique path to a new temporary file.
     *
     * @param string $dir The directory where the new file will be created.
     *
     * @return string The file path.
     *
     * @throws RuntimeException If problem retrieving.
     */
    protected function getTempFilePath(string $dir, string $prefix = ''): string
    {
        $dir = rtrim($dir, '/');

        do {
            $fileName = uniqid($prefix);
            $filePath = sprintf('%1$s/%2$s', $dir, $fileName);
        } while (file_exists($filePath));

        @fclose(fopen($filePath, 'a'));

        return $filePath;
    }
}
