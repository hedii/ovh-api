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
        $endpoint = getenv('OVH_ENDPOINT');
        $consumerKey = getenv('OVH_CONSUMER_KEY');

        if (! $appKey || ! $appSecret || ! $endpoint || ! $consumerKey) {
            throw new Exception('You need to setup the api credentials in phpunit.xml before running the test suite');
        }

        $this->api = new OvhApi($appKey, $appSecret, $endpoint, $consumerKey);
    }

    /** @test */
    public function it_should_get_me(): void
    {
        $me = $this->api->get('/me');

        $this->assertIsArray($me);
        $this->assertNotEmpty($me['nichandle']);
    }
}