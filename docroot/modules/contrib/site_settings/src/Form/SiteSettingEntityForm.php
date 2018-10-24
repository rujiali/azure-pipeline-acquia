<?php

namespace Drupal\site_settings\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\site_settings\Entity\SiteSettingEntityType;
use Drupal\site_settings\Entity\SiteSettingEntity;

/**
 * Form controller for Site Setting edit forms.
 *
 * @ingroup site_settings
 */
class SiteSettingEntityForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\site_settings\Entity\SiteSettingEntity */
    $form = parent::buildForm($form, $form_state);
    $site_settings_entity_type = SiteSettingEntityType::load($this->entity->getType());

    $form['heading1'] = [
      '#markup' => '<h2>' . $site_settings_entity_type->get('label') . '</h2>',
      '#weight' => -100,
    ];

    // Set entity title and fieldset to match the bundle.
    $form['name']['widget'][0]['value']['#value'] = $site_settings_entity_type->get('label');
    $form['fieldset']['widget'][0]['value']['#value'] = $site_settings_entity_type->get('fieldset');

    // Hide fields.
    hide($form['name']);
    hide($form['user_id']);
    hide($form['fieldset']);
    if (isset($form['multiple'])) {
      hide($form['multiple']);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $entity = $this->entity;
    $entity_bundle = $entity->bundle();
    $entity_type = $entity->getEntityType()->getBundleEntityType();
    $entity_type_manager = \Drupal::entityTypeManager();

    // Get existing entities in this settings bundle.
    $query = $entity_type_manager->getStorage('site_setting_entity')->getQuery();
    $query->condition('type', $entity_bundle);
    $existing = $query->execute();
    $bundle = $entity_type_manager->getStorage($entity_type)->load($entity_bundle);

    if (!$bundle->multiple) {
      if (count($existing) > 0 && $entity->id() != reset($existing)) {
        $form_state->setErrorByName('name', $this->t('There can only be one of this setting.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    // Save the entity.
    $entity = $this->entity;
    $entity->set('fieldset', $values['fieldset']);
    $entity->set('user_id', $values['user_id'][0]['target_id']);
    $entity->save();

    // Save the form.
    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        drupal_set_message($this->t('Created the %label Site Setting.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label Site Setting.', [
          '%label' => $entity->label(),
        ]));
    }

    // Clear the site settings cache.
    $site_settings = \Drupal::service('site_settings.loader');
    $site_settings->clearCache();

    $form_state->setRedirect('entity.site_setting_entity.collection');
  }

}
