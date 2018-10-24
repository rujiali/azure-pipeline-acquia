<?php

/**
 * @file
 * Drush commands for menu item extras.
 */

/**
 * @defgroup menu_item_extras_drush Menu Item Extras Drush Integration
 *
 * @{
 * Drush command integration for Menu Item Extras.
 *
 * These are basically wrappers around the functions that can be called on the
 * Operations page.
 *
 * @see http://www.drush.org/en/master/
 */

/**
 * Implements hook_drush_command().
 */
function menu_item_extras_drush_command() {
  $items = [];
  $items['menu-item-extras-clear-extra-data'] = [
    'description' => dt('Clear a specific menu extra data or in all menus.'),
    'arguments' => [
      'machine_menu_name' => 'The machine name of the menu',
    ],
    'required-arguments' => TRUE,
    'examples' => [
      'drush menu-item-extras-clear-extra-data "menu_name"' => dt('Clears extra data for `menu_name` menu.'),
      'drush menu-item-extras-clear-extra-data all' => dt('Clears extra data for all menus.'),
    ],
    'aliases' => ['mie-ced'],
  ];
  return $items;
}

/**
 * Callback function for drush menu-item-extras-clear-extra-data.
 */
function drush_menu_item_extras_clear_extra_data($machine_menu_name) {
  \Drupal::service('menu_item_extras.menu_link_content_helper')->clearMenuData($machine_menu_name);
  if ($machine_menu_name === 'all') {
    drush_print(dt('Extra data for all menus were deleted.', [
      '@label' => $machine_menu_name,
    ]));
  }
  else {
    drush_print(dt('Extra data for `@label` menu was deleted.', [
      '@label' => $machine_menu_name,
    ]));
  }
}

/**
 * @} End of "defgroup menu_item_extras_drush".
 */
