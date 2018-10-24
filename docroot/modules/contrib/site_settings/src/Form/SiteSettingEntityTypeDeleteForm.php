<?php

namespace Drupal\site_settings\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Builds the form to delete Site Setting type entities.
 */
class SiteSettingEntityTypeDeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete %name?', ['%name' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.site_setting_entity_type.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Get site settings of this type.
    $query = \Drupal::entityQuery('site_setting_entity');
    $query->condition('type', $this->entity->id());
    if ($entity_ids = $query->execute()) {

      // Delete site settings of this type.
      $controller = \Drupal::entityManager()->getStorage('site_setting_entity');
      $entities = $controller->loadMultiple($entity_ids);
      $controller->delete($entities);
    }

    // Delete the site setting entity type.
    $this->entity->delete();

    drupal_set_message($this->t('Successfully deleted the "@label" site setting.', [
      '@label' => $this->entity->label(),
    ]));

    // Rebuild the site settings cache.
    $site_settings = \Drupal::service('site_settings.loader');
    $site_settings->clearCache();

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
