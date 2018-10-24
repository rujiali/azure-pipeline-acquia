<?php

namespace Drupal\Tests\menu_item_extras\Functional;

use Drupal\Tests\menu_link_content\Functional\LinksTest;

/**
 * Tests handling of menu links hierarchies.
 *
 * @group menu_item_extras
 */
class LinksOriginTest extends LinksTest {

  /**
   * {@inheritdoc}
   */
  public function __construct($name = NULL, array $data = [], $dataName = '') {
    static::$modules[] = 'menu_item_extras';
    parent::__construct($name, $data, $dataName);
  }

}
