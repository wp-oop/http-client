<?php

namespace WpOop\HttpClient;

use Nyholm\Psr7\Stream;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use WP_Error;

/**
 * A PSR-18 HTTP client that uses the WordPress HTTP API.
 *
 * @see https://developer.wordpress.org/reference/functions/wp_remote_request/
 *
 * @psalm-type WpRequestOptions = array{
 *    timeout?: int,
 *    redirection?: int,
 *    user-agent?: string,
 *    reject_unsafe_urls?: bool,
 *    blocking?: bool,
 *    compress?: bool,
 *    decompress?: bool,
 *    sslverify?: bool,
 *    sslcertificates?: string,
 *    stream?: bool,
 *    filename?: string,
 *    limit_response_size?: int,
 *   }
 */
class Client implements ClientInterface
{
    protected array $wpOptions;
    protected ResponseFactoryInterface $responseFactory;

    public function __construct(
        array $wpOptions,
        ResponseFactoryInterface $responseFactory
    ) {
        $this->wpOptions = $wpOptions;
        $this->responseFactory = $responseFactory;
    }

    /** @inheritDoc */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $uri = (string)$request->getUri();
        $args = $this->prepareArgs($request);
        $httpVer = $request->getProtocolVersion();

        /** @var WP_Error|array $responseData */
        $responseData = wp_remote_request($uri, $args);

        $code = wp_remote_retrieve_response_code($responseData);
        $code = is_numeric($code) ? (int)$code : 400;
        $reason = wp_remote_retrieve_response_message($responseData);
        $headers = wp_remote_retrieve_headers($responseData);
        $headers = is_array($headers) ? $headers : iterator_to_array($headers);
        $body = wp_remote_retrieve_body($responseData);

        return $this->createResponse($code, $headers, $body, $httpVer, $reason);
    }

    /**
     * Prepares the args array for a specific request. The result can be used with WordPress' remote functions.
     *
     * @param RequestInterface $request The request.
     *
     * @return WpRequestOptions The prepared args array.
     */
    protected function prepareArgs(RequestInterface $request): array
    {
        return array_merge($this->wpOptions, [
            'method' => $request->getMethod(),
            'httpversion' => $request->getProtocolVersion(),
            'headers' => $this->prepareHeaders($request),
            'body' => (string)$request->getBody(),
        ]);
    }

    /**
     * Transforms a request's headers into the format expected by WordPress' remote functions.
     *
     * @param RequestInterface $request The request.
     *
     * @return array<string, string> The prepared headers array.
     */
    protected function prepareHeaders(RequestInterface $request): array
    {
        $headers = [];

        foreach ($request->getHeaders() as $header => $values) {
            $headers[$header] = $request->getHeaderLine($header);
        }

        return $headers;
    }

    /**
     * @param int $code
     * @param array<string, scalar|array<scalar>> $headers A map of header names to header value(s).
     * @param string|StreamInterface $body
     * @param string $httpVer
     * @param string $reason
     * @return void
     */
    protected function createResponse(
        int $code,
        array $headers,
        $body,
        string $httpVer,
        string $reason
    ): ResponseInterface {
        $factory = $this->responseFactory;
        $response = $factory->createResponse($code, $reason);

        // Convert contents to stream
        if (is_scalar($body)) {
            $stream = @fopen('php://temp', 'r+');
            if ($body !== '') {
                fwrite($stream, (string) $body);
                fseek($stream, 0);
            }

            $body = new Stream($stream);
        }

        // Set headers
        foreach ($headers as $headerName => $headerValue) {
            $response = $response->withHeader($headerName, $headerValue);
        }

        $response = $response->withBody($body);
        $response = $response->withProtocolVersion($httpVer);

        return $response;
    }
}