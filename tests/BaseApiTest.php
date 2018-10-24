<?php

namespace Tests\Api;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;

class BaseApiTest extends TestCase
{
    protected $client;
    protected $domain;


    public function setup()
    {
        $this->client = new Client;
        if (getenv('CI_TEST_DOMAIN')) {
          $this->domain = getenv('CI_TEST_DOMAIN');
        }
        else {
          $this->domain = 'http://worksafe.vm';
        }
    }

	/**
	 * Get body of response
	 *
	 * @param GuzzleHttp/Client $response
	 * @return array
	 */
    protected function getBody($response)
    {
        return json_decode($response->getBody()->getContents(), true);
    }

	/**
	 * PHPUnit doesn't traverse into an array as expected so implementing this method instead.
     *
     * @todo This method could be improved as it only goes two levels deep.
	 *
	 * @param array $expectedSubset - The section we hope to find
	 * @param array $arraySet - The array to earch through
	 * @param string $message
	 */
    protected function assertArraySetContainsSubset($expectedSubset, $arraySet, $message = '')
    {
        if ($message == '') {
            $message = 'Failed asserting that an array contains another array that contains at least ' . print_r($expectedSubset, true);
        }

        foreach ($arraySet as $array) {
            foreach ($expectedSubset as $expectedKey => $expectedValue) {
                if ($array[$expectedKey] != $expectedValue) {
                    continue 2;
                }
            }
            $this->assertTrue(true);
            return;
        }
        $this->fail($message);
    }
}
