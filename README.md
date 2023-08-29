# WP HTTP Client
[![Continuous Integration](https://img.shields.io/github/actions/workflow/status/wp-oop/http-client/continuous-integration.yml?logo=github&logoColor=FFFFFF)][github-workflow]
[![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/wp-oop/http-client)][packagist]
[![Latest Stable Version](https://poser.pugx.org/wp-oop/http-client/v)][packagist]
[![Latest Unstable Version](https://poser.pugx.org/wp-oop/http-client/v/unstable)][packagist]

A [PSR-18][psr-18] wrapper for the [WordPress HTTP API][wp-http-api].

## Usage

```php
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use WpOop\HttpClient\Client;

/** @var RequestFactoryInterface $requestFactory */
/** @var ResponseFactoryInterface $responseFactory */

// Set up the client with WP request options
// https://developer.wordpress.org/reference/classes/wp_http/request/
$client = new Client(
    [
    'redirection' => 2,
    'timeout' => 60,
    ],
    $responseFactory
);

// Create a PSR-7 request in any way you want, for example via a PSR-17 factory
$request = $requestFactory->createRequest('GET', 'http://somesite.com/api');

try {
    // Send a request as usual, consuming a familiar PSR-18 interface
    $response = $client->sendRequest($request);
} catch (ClientExceptionInterface $e) {
    // Handle PSR-18 exceptions
}
```

Since this is a [PSR-18][]-compliant implementation, you can consume it in the way you would any other.

To set it up, pass a map of WP request options, and a [PSR-17][] response factory. This approach facilitates
decoupling from any concrete [PSR-7][] implementation.

You can use any [PSR-17 implementation][psr-17-implementations] or [PSR-7 implementations][psr-7-implementations].
I suggest the slim and efficient [nyholm/psr7][], which conveniently implements both.

## Limitations
Currently only throws [`ClientExceptionInterface`][], as it is unable to reliably determine whether a network or
another specific kind of problem has occurred from the error value returned by [`wp_remote_request()`][].


[packagist]: https://packagist.org/packages/wp-oop/http-client
[wp-http-api]: https://developer.wordpress.org/plugins/http-api/
[psr-18]: https://www.php-fig.org/psr/psr-18/
[psr-17]: https://www.php-fig.org/psr/psr-17/
[psr-7]: https://www.php-fig.org/psr/psr-7/
[psr-17-implementations]: https://packagist.org/providers/psr/http-factory-implementation
[psr-7-implementations]: https://packagist.org/providers/psr/http-message-implementation
[nyholm/psr7]: https://packagist.org/packages/nyholm/psr7
[github-workflow]: https://github.com/wp-oop/http-client/actions/workflows/continuous-integration.yml
[`wp_remote_request()`]: https://developer.wordpress.org/reference/functions/wp_remote_request/
[`ClientExceptionInterface`]: https://github.com/php-fig/http-client/blob/master/src/ClientExceptionInterface.php
