<?php

namespace Drupal\menu_item_extras\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\menu_link_content\Entity\MenuLinkContent;

/**
 * {@inheritdoc}
 */
class MenuItemExtrasMenuLinkContent extends MenuLinkContent implements MenuItemExtrasMenuLinkContentInterface {

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
    if (isset($values['menu_name'])) {
      $values += ['bundle' => $values['menu_name']];
    }
    parent::preCreate($storage, $values);
  }

}
