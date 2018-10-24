<?php

namespace Drupal\site_settings\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides a 'SimpleSiteSettingsBlock' block.
 *
 * @Block(
 *  id = "simple_site_settings_block",
 *  admin_label = @Translation("Simple site settings block"),
 * )
 */
class SimpleSiteSettingsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Construct.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'setting' => NULL,
      'label_display' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {

    // Allow selection of a site settings entity type.
    $form['setting'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'site_setting_entity_type',
      '#title' => $this->t('Site setting type'),
      '#weight' => '20',
      '#required' => TRUE,
    ];
    if (isset($this->configuration['setting']) && !empty($this->configuration['setting'])) {
      $setting_entity_type = $this->entityTypeManager
        ->getStorage('site_setting_entity_type')
        ->load($this->configuration['setting']);
      $form['setting']['#default_value'] = $setting_entity_type;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['setting'] = $form_state->getValue('setting');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $base_fields = [];

    // Get the renderer for a basic rendering. Users can use the templating
    // system to do something more advanced.
    $site_settings_renderer = \Drupal::service('site_settings.renderer');
    $site_settings_renderer->setDefaultImageSizeOutput(400, 400);

    // Get all settings in the selected bundle.
    $entity_ids = \Drupal::entityQuery('site_setting_entity')
      ->condition('type', $this->configuration['setting'])
      ->execute();
    if ($entity_ids) {
      $entities = \Drupal::entityTypeManager()
        ->getStorage('site_setting_entity')
        ->loadMultiple($entity_ids);

      // Loop through the entities and their fields.
      foreach ($entities as $entity) {

        // Determine which fields to exclude from render.
        if (!$base_fields) {
          $base_fields = array_keys($entity->getEntityType()->getKeys());
          $base_fields = array_merge($base_fields, [
            'name',
            'user_id',
            'type',
            'created',
            'changed',
          ]);
        }

        $fields = $entity->getFields();
        foreach ($fields as $key => $field) {

          // Exclude base fields from output.
          if (!in_array($key, $base_fields) && method_exists(get_class($field), 'getFieldDefinition')) {
            $build[] = [
              '#markup' => $site_settings_renderer->renderField($field),
            ];
          }
        }
      }
    }

    return $build;
  }

}
