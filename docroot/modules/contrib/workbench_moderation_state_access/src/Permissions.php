<?php

namespace Drupal\workbench_moderation_state_access;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\workbench_moderation\Entity\ModerationState;

/**
 * Defines a class for dynamic permissions based on states.
 */
class Permissions {

  use StringTranslationTrait;

  /**
   * Returns an array of edit permissions.
   *
   * @return array
   *   The edit permissions.
   */
  public function editPermissions() {
    // @todo write a test for this.
    $perms = [];
    /* @var \Drupal\workbench_moderation\ModerationStateInterface $state */
    foreach (ModerationState::loadMultiple() as $id => $state) {
      $perms['edit content in the ' . $id . ' state'] = [
        'title' => $this->t('Edit content when in the %state_name state.', [
          '%state_name' => $state->label(),
        ]),
      ];
    }

    return $perms;
  }

}
