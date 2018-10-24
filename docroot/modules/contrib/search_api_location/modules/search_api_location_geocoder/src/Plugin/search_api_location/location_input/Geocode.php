<?php

namespace Drupal\search_api_location_geocoder\Plugin\search_api_location\location_input;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\geocoder\Geocoder;
use Drupal\search_api_location\LocationInput\LocationInputPluginBase;
use Drupal\Component\Utility\SortArray;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Represents the Raw Location Input.
 *
 * @LocationInput(
 *   id = "geocode",
 *   label = @Translation("Geocoded input"),
 *   description = @Translation("Let user enter an address that will be geocoded."),
 * )
 */
class Geocode extends LocationInputPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The geocoder service.
   *
   * @var \Drupal\geocoder\Geocoder
   */
  protected $geocoder;

  /**
   * Constructs a Geocode Location input Plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\geocoder\Geocoder $geocoder
   *   The geocoder service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Geocoder $geocoder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->geocoder = $geocoder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('geocoder'));
  }

  /**
   * {@inheritdoc}
   */
  public function getParsedInput(array $input) {
    if (empty($input['value'])) {
      throw new \InvalidArgumentException('Input doesn\'t contain a location value.');
    }
    else {
      $active_plugins = $this->getActivePlugins();
      /** @var \Geocoder\Model\AddressCollection $geocoded_addresses */
      $geocoded_addresses = $this->geocoder
        ->geocode($input['value'], $active_plugins);
      if ($geocoded_addresses) {
        return $geocoded_addresses->first()
          ->getLatitude() . ',' . $geocoded_addresses->first()
          ->getLongitude();
      }
    }
    return NULL;
  }

  /**
   * Gets the active geocoder plugins.
   */
  protected function getActivePlugins() {
    $plugins = $this->configuration['plugins'];
    uasort($plugins, [SortArray::class, 'sortByWeightProperty']);

    $active_plugins = [];
    foreach ($plugins as $id => $plugin) {
      if ($plugin['checked']) {
        $active_plugins[$id] = $id;
      }
    }

    return $active_plugins;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $configuration = parent::defaultConfiguration();
    $configuration['plugins'] = [];

    $geocoderpluginmanager = \Drupal::service('plugin.manager.geocoder.provider');

    foreach ($geocoderpluginmanager->getPluginsAsOptions() as $plugin_id => $plugin_name) {
      $configuration['plugins'][$plugin_id]['checked'] = 0;
      $configuration['plugins'][$plugin_id]['weight'] = 0;
    }

    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $geocoderpluginmanager = \Drupal::service('plugin.manager.geocoder.provider');

    $form['plugins'] = [
      '#type' => 'table',
      '#header' => [$this->t('Geocoder plugins'), $this->t('Weight')],
      '#tabledrag' => [[
        'action' => 'order',
        'relationship' => 'sibling',
        'group' => 'plugins-order-weight',
      ],
      ],
      '#caption' => $this->t('Select the Geocoder plugins to use, you can reorder them. The first one to return a valid value will be used.'),
    ];

    foreach ($geocoderpluginmanager->getPluginsAsOptions() as $plugin_id => $plugin_name) {
      $form['plugins'][$plugin_id] = [
        'checked' => [
          '#type' => 'checkbox',
          '#title' => $plugin_name,
          '#default_value' => $this->configuration['plugins'][$plugin_id]['checked'],
        ],
        'weight' => [
          '#type' => 'weight',
          '#title' => $this->t('Weight for @title', ['@title' => $plugin_name]),
          '#title_display' => 'invisible',
          '#attributes' => ['class' => ['plugins-order-weight']],
          '#default_value' => $this->configuration['plugins'][$plugin_id]['weight'],
        ],
        '#attributes' => ['class' => ['draggable']],
      ];
    }

    $form += parent::buildConfigurationForm($form, $form_state);

    return $form;
  }

  /**
   * Form validation handler.
   *
   * @param array $form
   *   An associative array containing the structure of the plugin form as built
   *   by static::buildConfigurationForm().
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the complete form.
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // TODO: Implement validateConfigurationForm() method.
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the plugin form as built
   *   by static::buildConfigurationForm().
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the complete form.
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // TODO: Implement submitConfigurationForm() method.
  }

}
