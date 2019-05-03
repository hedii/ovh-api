<?php

namespace Hedii\OvhApi\Tests;

use Exception;
use Hedii\OvhApi\OvhApi;
use PHPUnit\Framework\TestCase;

class OvhApiTest extends TestCase
{
    /**
     * The OvhApi instance.
     *
     * @var \Hedii\OvhApi\OvhApi
     */
    protected $api;

    /**
     * This method is called before each test.
     *
     * @throws \Exception
     */
    public function setUp(): void
    {
        parent::setUp();

        $appKey = getenv('OVH_APPLICATION_KEY');
        $appSecret = getenv('OVH_APPLICATION_SECRET');
        $consumerKey = getenv('OVH_CONSUMER_KEY');
        $endpoint = getenv('OVH_ENDPOINT');

        if (! $appKey || ! $appSecret || ! $consumerKey || ! $endpoint) {
            throw new Exception('You need to setup the api credentials in phpunit.xml before running the test suite');
        }

        $this->api = new OvhApi($appKey, $appSecret, $consumerKey, $endpoint);
    }

    /** @test */
    public function it_should_get_me(): void
    {
        $me = $this->api->get('/me');

        $this->assertIsArray($me);
        $this->assertNotEmpty($me['nichandle']);
    }

    /** @test */
    public function it_should_get_me_concurrently(): void
    {
        $me = $this->api->concurrentGet([
            ['path' => '/me'],
            ['path' => '/me']
        ]);

        $this->assertIsArray($me);
        $this->assertCount(2, $me);
        $this->assertNotEmpty($me[0]['nichandle']);
        $this->assertNotEmpty($me[1]['nichandle']);
    }
}
