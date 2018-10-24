<?php

namespace Drupal\Tests\graphql_core\Kernel;

use Drupal\Tests\graphql\Kernel\GraphQLTestBase;

/**
 * Test base for drupal core graphql functionality.
 */
class GraphQLCoreTestBase extends GraphQLTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'graphql_core',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function defaultCacheTags() {
    return array_merge([
      'entity_bundles',
      'entity_types',
      'entity_field_info',
    ], parent::defaultCacheTags());
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    // User entity schema is required for the currentUserContext field.
    $this->installEntitySchema('user');
  }

}
