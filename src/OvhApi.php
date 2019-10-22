<?php

namespace Hedii\OvhApi;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\EachPromise;
use Psr\Http\Message\ResponseInterface;

class OvhApi
{
    /**
     * Url to communicate with Ovh API.
     *
     * @var array
     */
    protected $endpoints = [
        'ovh-eu' => 'https://eu.api.ovh.com/1.0/',
        'ovh-ca' => 'https://ca.api.ovh.com/1.0/',
        'ovh-us' => 'https://api.us.ovhcloud.com/1.0/',
        'kimsufi-eu' => 'https://eu.api.kimsufi.com/1.0/',
        'kimsufi-ca' => 'https://ca.api.kimsufi.com/1.0/',
        'soyoustart-eu' => 'https://eu.api.soyoustart.com/1.0/',
        'soyoustart-ca' => 'https://ca.api.soyoustart.com/1.0/',
        'runabove-ca' => 'https://api.runabove.com/1.0/'
    ];

    /**
     * The application key.
     *
     * @var string
     */
    protected $appKey;

    /**
     * The application secret.
     *
     * @var string
     */
    protected $appSecret;

    /**
     * The application consumer key.
     *
     * @var string
     */
    protected $consumerKey;

    /**
     * The api endpoint.
     *
     * @var string
     */
    protected $endpoint;

    /**
     * The http client instance.
     *
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * The delta between local timestamp and api server timestamp.
     *
     * @var null|string
     */
    protected $timeDelta = null;

    /**
     * OvhApi constructor.
     *
     * @param string $appKey
     * @param string $appSecret
     * @param string $consumerKey
     * @param string $endpoint
     */
    public function __construct(string $appKey, string $appSecret, string $consumerKey, string $endpoint) {
        $this->appKey = $appKey;
        $this->appSecret = $appSecret;
        $this->consumerKey = $consumerKey;
        $this->endpoint = $endpoint;
        $this->client = $this->createClient();
    }

    /**
     * Make a GET request.
     *
     * @param string $path
     * @param array $content
     * @return null|array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function get(string $path, array $content = []): ?array
    {
        return $this->rawCall('GET', $path, $content);
    }

    /**
     * Make a POST request.
     *
     * @param string $path
     * @param array $content
     * @return null|array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function post(string $path, array $content = []): ?array
    {
        return $this->rawCall('POST', $path, $content);
    }

    /**
     * Make a PUT request.
     *
     * @param string $path
     * @param array $content
     * @return null|array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function put(string $path, array $content = []): ?array
    {
        return $this->rawCall('PUT', $path, $content);
    }

    /**
     * Make a DELETE request.
     *
     * @param string $path
     * @param array $content
     * @return null|array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function delete(string $path, array $content = []): ?array
    {
        return $this->rawCall('DELETE', $path, $content);
    }

    /**
     * Make concurrent GET requests.
     *
     * @param array $requests
     * @param int $concurrency
     * @return null|array
     */
    public function concurrentGet(array $requests, int $concurrency = 10): ?array
    {
        return $this->concurrentRawRequest('GET', $requests, $concurrency);
    }

    /**
     * Make concurrent POST requests.
     *
     * @param array $requests
     * @param int $concurrency
     * @return null|array
     */
    public function concurrentPost(array $requests, int $concurrency = 10): ?array
    {
        return $this->concurrentRawRequest('POST', $requests, $concurrency);
    }

    /**
     * Make concurrent PUT requests.
     *
     * @param array $requests
     * @param int $concurrency
     * @return null|array
     */
    public function concurrentPut(array $requests, int $concurrency = 10): ?array
    {
        return $this->concurrentRawRequest('PUT', $requests, $concurrency);
    }

    /**
     * Make concurrent DELETE requests.
     *
     * @param array $requests
     * @param int $concurrency
     * @return null|array
     */
    public function concurrentDelete(array $requests, int $concurrency = 10): ?array
    {
        return $this->concurrentRawRequest('DELETE', $requests, $concurrency);
    }

    /**
     * Make a request.
     *
     * @param string $method
     * @param string $path
     * @param array $content
     * @return null|array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function rawCall(string $method, string $path, array $content = []): ?array
    {
        $body = $this->formatBody($method, $content);
        $query = $this->formatQuery($method, $content);

        $response = $this->client->request($method, $this->formatPath($path), [
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8',
                'X-Ovh-Application' => $this->appKey,
                'X-Ovh-Consumer' => $this->consumerKey,
                'X-Ovh-Timestamp' => $this->timestamp(),
                'X-Ovh-Signature' => $this->signature($method, $path, $body, $query)
            ],
            'query' => $query,
            'body' => $body
        ]);

        return $this->decodeResponse($response);
    }

    /**
     * Make concurrent requests.
     *
     * @param string $method
     * @param array $requests
     * @param int $concurrency
     * @return null|array
     */
    public function concurrentRawRequest(string $method, array $requests, int $concurrency): ?array
    {
        $responses = [];

        $promises = (function () use ($method, $requests) {
            foreach ($requests as $request) {
                $request['content'] = $request['content'] ?? [];

                $body = $this->formatBody($method, $request['content']);
                $query = $this->formatQuery($method, $request['content']);

                yield $this->client->requestAsync($method, $this->formatPath($request['path']), [
                    'headers' => [
                        'Content-Type' => 'application/json; charset=utf-8',
                        'X-Ovh-Application' => $this->appKey,
                        'X-Ovh-Consumer' => $this->consumerKey,
                        'X-Ovh-Timestamp' => $this->timestamp(),
                        'X-Ovh-Signature' => $this->signature($method, $request['path'], $body, $query)
                    ],
                    'query' => $query,
                    'body' => $body
                ])->then(function (ResponseInterface $response): ResponseInterface {
                    return $response;
                });
            }
        })();

        $each = new EachPromise($promises, [
            'concurrency' => $concurrency,
            'fulfilled' => function (ResponseInterface $response, int $index) use (&$responses): void {
                $responses[$index] = $this->decodeResponse($response);
            },
            'rejected' => function (Exception $exception): void {
                throw $exception;
            }
        ]);

        $each->promise()->wait();

        return $responses;
    }

    /**
     * Create a new http client instance.
     *
     * @return \GuzzleHttp\Client
     */
    protected function createClient(): Client
    {
        return new Client([
            'base_uri' => $this->endpoints[$this->endpoint],
            'timeout' => 30,
            'connect_timeout' => 5
        ]);
    }

    /**
     * Calculate time delta between local machine and API's server.
     *
     * @return int
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function calculateTimeDelta(): int
    {
        if ($this->timeDelta === null) {
            $serverTimestamp = (int) $this->client->request('GET', $this->formatPath('/auth/time'))
                ->getBody()
                ->getContents();

            $this->timeDelta = $serverTimestamp - time();
        }

        return $this->timeDelta;
    }

    /**
     * Generate the signature.
     *
     * @param string $method
     * @param string $path
     * @param string $body
     * @param array $query
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function signature(string $method, string $path, string $body, array $query): string
    {
        $url = $this->endpoints[$this->endpoint] . $this->formatPath($path);

        if ($method === 'GET' && $queryString = http_build_query($query)) {
            $url .= "?{$queryString}";
        }

        $toSign = "{$this->appSecret}+{$this->consumerKey}+{$method}+{$url}+{$body}+{$this->timestamp()}";

        return '$1$' . sha1($toSign);
    }

    /**
     * Generate the timestamp.
     *
     * @return int
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function timestamp(): int
    {
        return $this->calculateTimeDelta() + time();
    }

    /**
     * Format the request path.
     *
     * @param string $path
     * @return string
     */
    protected function formatPath(string $path): string
    {
        return ltrim($path, '/');
    }

    /**
     * Format the request query.
     *
     * @param string $method
     * @param array $content
     * @return array
     */
    protected function formatQuery(string $method, array $content): array
    {
        if ($method !== 'GET') {
            return [];
        }

        foreach ($content as $key => $value) {
            if ($value === false) {
                $content[$key] = 'false';
            } elseif ($value === true) {
                $content[$key] = 'true';
            }
        }

        return $content;
    }

    /**
     * Format the request body.
     *
     * @param string $method
     * @param array $content
     * @return string
     */
    protected function formatBody(string $method, array $content): string
    {
        if ($method === 'GET' || ! $content) {
            return '';
        }

        return json_encode($content, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Decode a json response to an array.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return null|array
     */
    protected function decodeResponse(ResponseInterface $response): ?array
    {
        if ($content = $response->getBody()->getContents()) {
            return json_decode($content, true);
        }

        return null;
    }
}
