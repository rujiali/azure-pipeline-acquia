<?php

namespace Drupal\site_settings\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\KeyValueStore\KeyValueDatabaseFactory;

/**
 * Class SiteSettingsConfigForm.
 *
 * @package Drupal\site_settings\Form
 */
class SiteSettingsConfigForm extends ConfigFormBase {

  /**
   * Drupal\Core\KeyValueStore\KeyValueDatabaseFactory definition.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueDatabaseFactory
   */
  protected $keyvalueDatabase;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    KeyValueDatabaseFactory $keyvalue_database
  ) {
    parent::__construct($config_factory);
    $this->keyvalueDatabase = $keyvalue_database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('keyvalue.database')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'site_settings.config',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'site_settings_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('site_settings.config');

    // Global setting.
    $form['template_key'] = [
      '#type' => 'machine_name',
      '#title' => t('Template key'),
      '#description' => t('The key at which site settings should be made available in templates such as {{ site_settings.your_settings_group.your_setting_name }} with a template key of "site_settings".'),
      '#default_value' => $config->get('template_key'),
      '#required' => TRUE,
      '#machine_name' => [
        'exists' => [$this, 'machineNameExists'],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Machine name validation callback.
   *
   * This method needs to exist, but there can be only one so it never exists.
   *
   * @param string $value
   *   The input value.
   *
   * @return bool
   *   That the machine name does not exist.
   */
  public function machineNameExists($value) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config('site_settings.config')
      ->set('template_key', $form_state->getValue('template_key'))
      ->save();
  }

}
