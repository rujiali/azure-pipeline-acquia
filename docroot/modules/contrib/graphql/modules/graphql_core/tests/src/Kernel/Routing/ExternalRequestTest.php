<?php

namespace Drupal\Tests\graphql_core\Kernel\Routing;

use Drupal\Tests\graphql_core\Kernel\GraphQLCoreTestBase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;

/**
 * Test external requests.
 *
 * @group graphql_core
 */
class ExternalRequestTest extends GraphQLCoreTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['graphql_core'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('user');
  }

  /**
   * Test external requests.
   */
  public function testExternalRequests() {
    $client = $this->prophesize(ClientInterface::class);
    $client->request('GET', 'http://drupal.graphql')->willReturn(new Response(
      200,
      ['graphql' => 'test'],
      '<p>GraphQL is awesome!</p>'
    ));

    $this->container->set('http_client', $client->reveal());

    // TODO: Check cache metadata.
    // Add cache information from external response?
    $metadata = $this->defaultCacheMetaData();
    $metadata->setCacheTags(array_diff($metadata->getCacheTags(), ['entity_bundles']));

    $this->assertResults($this->getQueryFromFile('external_requests.gql'), [], [
      'route' => [
        'request' => [
          'code' => 200,
          'content' => '<p>GraphQL is awesome!</p>',
          'header' => 'test',
        ],
      ],
    ], $metadata);
  }

}
