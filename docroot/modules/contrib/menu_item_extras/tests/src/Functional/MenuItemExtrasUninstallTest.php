<?php

namespace Drupal\Tests\menu_item_extras\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests that uninstalling module does not break anything.
 *
 * @group menu_item_extras
 */
class MenuItemExtrasUninstallTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['menu_item_extras'];

  /**
   * Tests module uninstall.
   */
  public function testMenuItemExtrasUninstall() {
    \Drupal::service('module_installer')->uninstall(['menu_item_extras']);
  }

}
