<?php

namespace Drupal\site_settings\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for deleting Site Setting entities.
 *
 * @ingroup site_settings
 */
class SiteSettingEntityDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Clear the site settings cache.
    $site_settings = \Drupal::service('site_settings.loader');
    $site_settings->clearCache();

    // Submit the parent form.
    parent::submitForm($form, $form_state);
  }

}
