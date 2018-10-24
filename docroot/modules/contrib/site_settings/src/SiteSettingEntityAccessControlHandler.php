<?php

namespace Drupal\site_settings;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Site Setting entity.
 *
 * @see \Drupal\site_settings\Entity\SiteSettingEntity.
 */
class SiteSettingEntityAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\site_settings\SiteSettingEntityInterface $entity */
    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished site setting entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view published site setting entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit site setting entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete site setting entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add site setting entities');
  }

}
