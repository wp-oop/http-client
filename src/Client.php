<?php

declare(strict_types=1);

namespace WpOop\HttpClient;

use Nyholm\Psr7\Stream;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use UnexpectedValueException;
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
    /**
     * phpcs:ignore SlevomatCodingStandard.TypeHints.UselessConstantTypeHint.UselessVarAnnotation
     * @var string
     */
    public const REQUEST_FILE_PREFIX = 'wpx';
    /** @var WpRequestOptions */
    protected array $wpOptions;
    protected ResponseFactoryInterface $responseFactory;
    protected ?string $streamProxyDir = null;

    /**
     * @param WpRequestOptions $wpOptions
     * @param ?string $streamProxyDir Path to the directory where responses will be saved for streaming.
     *                                If null, no streaming will be used: response body is kept in memory.
     */
    public function __construct(
        array $wpOptions,
        ResponseFactoryInterface $responseFactory,
        ?string $streamProxyDir
    ) {
        $this->wpOptions = $wpOptions;
        $this->responseFactory = $responseFactory;
        $this->streamProxyDir = $streamProxyDir;
    }

    /** @inheritDoc */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $uri = (string)$request->getUri();
        $bodyFile =  $this->streamProxyDir !== null
            ? $this->getTempFilePath($this->streamProxyDir)
            : null;
        $args = $this->prepareArgs($request, $bodyFile);
        $httpVer = $request->getProtocolVersion();

        /** @var WP_Error|array $responseData */
        $responseData = wp_remote_request($uri, $args);
        if ($responseData instanceof WP_Error) {
            throw new ClientException(
                sprintf(
                    'Could not send request to "%1$s": %2$s',
                    $uri,
                    $responseData->get_error_message()
                )
            );
        }

        $code = wp_remote_retrieve_response_code($responseData);
        if (!is_int($code)) {
            throw new ClientException(
                sprintf(
                    'Server at "%1$s" responded with an invalid code "%2$s"',
                    $uri,
                    $code
                )
            );
        }

        $reason = wp_remote_retrieve_response_message($responseData);
        $headers = wp_remote_retrieve_headers($responseData);
        $headers = is_array($headers) ? $headers : iterator_to_array($headers);
        $body = $bodyFile === null
            ? wp_remote_retrieve_body($responseData)
            : $this->createStreamFromFile($bodyFile, 'r');

        return $this->createResponse($code, $headers, $body, $httpVer, $reason);
    }

    /**
     * Prepares the args array for a specific request. The result can be used with WordPress' remote functions.
     *
     * @param RequestInterface $request The request.
     * @param ?string $streamProxyFile If specified, a new file with a unique name will be created there.
     *
     * @return WpRequestOptions&array{
     *     method: string,
     *     httpversion: string,
     *     headers: array<string, string|array<string>>,
     *     body: string,
     *     stream?: bool,
     *     filename?: string,
     * } The prepared args array.
     */
    protected function prepareArgs(RequestInterface $request, ?string $streamProxyFile = null): array
    {
        $args = array_merge($this->wpOptions, [
            'method' => $request->getMethod(),
            'httpversion' => $request->getProtocolVersion(),
            'headers' => $this->prepareHeaders($request),
            'body' => (string)$request->getBody(),
        ]);

        if ($streamProxyFile !== null) {
            $args['stream'] = true;
            $args['filename'] = $streamProxyFile;
        }

        return $args;
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
     * @param array<string, string|array<string>> $headers A map of header names to header value(s).
     * @param string|StreamInterface $body
     *
     * @throws RuntimeException If problem creating.
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

        // Ensure stream
        $body = ! $body instanceof StreamInterface
            ? $this->createStreamFromString($body)
            : $body;

        // Set headers
        foreach ($headers as $headerName => $headerValue) {
            $response = $response->withHeader($headerName, $headerValue);
        }

        $response = $response->withBody($body);
        $response = $response->withProtocolVersion($httpVer);

        return $response;
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
    protected function getTempFilePath(string $dir): string
    {
        $dir = rtrim($dir, '/');
        $mode = 'a';

        do {
            $fileName = uniqid(static::REQUEST_FILE_PREFIX);
            $filePath = sprintf('%1$s/%2$s', $dir, $fileName);
        } while (file_exists($filePath));

        $handle = @fopen($filePath, $mode);
        if ($handle === false) {
            throw new RuntimeException(sprintf('Could not open file "%1$s" with mode "%2$s"', $filePath, $mode));
        }

        @fclose($handle);

        return $filePath;
    }

    /**
     * Retrieves a standards-compliant stream that points to an open resource.
     *
     * @param resource $resource The stream resource to create a standard stream from.
     *
     * @return StreamInterface The standards-compliant stream.
     *
     * @throws RuntimeException If problem retrieving.
     */
    protected function createStreamFromResource($resource): StreamInterface
    {
        return new Stream($resource);
    }

    /**
     * Retrieves a new open stream that points to the file at the specified path.
     *
     * @param string $filePath The path to the file.
     * @param string $mode The mode to open the file in.
     *
     * @return StreamInterface The new open stream.
     *
     * @throws RuntimeException If problem retrieving.
     */
    protected function createStreamFromFile(string $filePath, string $mode): StreamInterface
    {
        $resource = @fopen($filePath, $mode);

        if ($resource === false) {
            throw new UnexpectedValueException('Could not open temporary memory stream');
        }

        $stream = $this->createStreamFromResource($resource);

        return $stream;
    }

    /**
     * Retrieves a standards-compliant stream with the specified contents.
     *
     * @param string $contents The contents for the stream.
     *
     * @return StreamInterface The stream.
     *
     * @throws RuntimeException If problem retrieving.
     */
    protected function createStreamFromString(string $contents): StreamInterface
    {
        $stream = $this->createStreamFromFile('php://temp', 'r+');
        $stream->write($contents);
        $stream->rewind();

        return $stream;
    }
}
