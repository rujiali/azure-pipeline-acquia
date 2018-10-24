<?php

namespace Drupal\linkit\Plugin\Linkit\Matcher;

/**
 * Provides specific linkit matchers for web forms.
 *
 * @Matcher(
 *   id = "entity:webform",
 *   label = @Translation("Web form"),
 *   target_entity = "webform",
 *   provider = "webform"
 * )
 */
class WebformMatcher extends EntityMatcher {

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return parent::calculateDependencies() + [
      'module' => ['webform'],
    ];
  }
}
