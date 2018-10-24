<?php

namespace Worksafe\Tests\Api;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use Tests\Api\BaseApiTest;

class SimpleTest extends BaseApiTest
{
    public function testTest()
    {
        return;

        // @todo example test broke...
        $nid = '4e43fb94-976c-4bc0-9b78-a3e54d3bf7cf';
        $response = $this->client->get($this->domain .'/jsonapi/node/page/'. $nid .'?_format=json');

        $this->assertEquals(200, $response->getStatusCode());
        $body = $this->getBody($response);

        $this->assertArraySetContainsSubset([
                'type' => 'node--page',
                'id' => $nid,
                //'attributes' => [
                //    'nid' => 'dad',
                //],
                                             
        ], $body);
    }
}
