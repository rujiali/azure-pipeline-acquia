<?php

namespace Drupal\site_settings;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Routing\LinkGeneratorTrait;
use Drupal\Core\Url;
use Drupal\site_settings\Entity\SiteSettingEntityType;
use Drupal\site_settings\Entity\SiteSettingEntity;

/**
 * Defines a class to build a listing of Site Setting entities.
 *
 * @ingroup site_settings
 */
class SiteSettingEntityListBuilder extends EntityListBuilder {

  use LinkGeneratorTrait;

  /**
   * Variable to store all bundles and be reduced by already set bundles.
   *
   * @var array
   */
  private $missingBundles = [];

  /**
   * Variable to store all bundles for quick access.
   *
   * @var array
   */
  private $bundles = [];

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Name');
    $header['fieldset'] = $this->t('Group');
    $header['value'] = $this->t('Value');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\site_settings\Entity\SiteSettingEntity */
    $row['name'] = $this->l(
      $entity->label(),
      new Url(
        'entity.site_setting_entity.edit_form', [
          'site_setting_entity' => $entity->id(),
        ]
      )
    );
    $entity_bundle = $entity->bundle();
    if ($bundle = SiteSettingEntityType::load($entity_bundle)) {
      $row['fieldset'] = $bundle->fieldset;
    }
    else {
      $row['fieldset'] = t('Unknown');
    }

    // Render the value of the field into the listing page.
    $row['value'] = '';
    $fields = $entity->getFields();
    $site_settings_renderer = \Drupal::service('site_settings.renderer');
    foreach ($fields as $key => $field) {
      if (method_exists(get_class($field), 'getFieldDefinition')) {
        $row['value'] = ($row['value'] ? '<br />' : '');
        $row['value'] = $site_settings_renderer->renderField($field);
      }
    }

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    $query = $this->getStorage()->getQuery()
      ->sort($this->entityType->getKey('fieldset'))
      ->sort($this->entityType->getKey('id'));
    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function render() {

    $entity_type = $this->entityType->getBundleEntityType();
    $this->bundles = \Drupal::entityTypeManager()
      ->getStorage($entity_type)
      ->loadMultiple();
    $this->missingBundles = array_keys($this->bundles);

    $site_settings = \Drupal::service('site_settings.loader');
    $variables['settings'] = $site_settings->loadAll(TRUE);

    $build['table'] = [
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#title' => $this->getTitle(),
      '#rows' => [],
      '#empty' => $this->t('There is no @label yet.', ['@label' => $this->entityType->getLabel()]),
      '#cache' => [
        'contexts' => $this->entityType->getListCacheContexts(),
        'tags' => $this->entityType->getListCacheTags(),
      ],
    ];

    $last_fieldset = FALSE;
    foreach ($this->load() as $entity) {

      // Get bundle type.
      $bundle_type = $entity->getType();
      $search = array_search($bundle_type, $this->missingBundles);
      if ($search !== FALSE) {
        unset($this->missingBundles[$search]);
      }

      // Set fieldset separator.
      $fieldset = $entity->fieldset->getValue()[0]['value'];
      if ($fieldset != $last_fieldset) {
        $heading = [
          '#markup' => '<strong>' . $fieldset . '</strong>',
        ];
        $build['table']['#rows'][$fieldset] = [
          'name' => \Drupal::service('renderer')->render($heading),
          'fieldset' => '',
          'value' => '',
          'operations' => '',
        ];
        $last_fieldset = $fieldset;
      }

      // Add table rows.
      if ($row = $this->buildRow($entity)) {
        $build['table']['#rows'][$entity->id()] = $row;
      }
    }

    if ($this->missingBundles) {
      usort($this->missingBundles, function ($a, $b) {
        if ($this->bundles[$a]->fieldset == $this->bundles[$b]->fieldset) {
          return ($this->bundles[$a]->label() >= $this->bundles[$b]->label()) ? -1 : 1;
        }
        return $this->bundles[$a]->fieldset >= $this->bundles[$b]->fieldset ? -1 : 1;
      });

      foreach ($this->missingBundles as $missing) {

        // Settings that have not yet been created rows.
        $url = new Url('entity.site_setting_entity.add_form', [
          'site_setting_entity_type' => $missing,
        ]);
        $link = [
          '#type' => 'link',
          '#title' => $this->t('Create setting'),
          '#url' => $url,
          '#attributes' => ['class' => ['button']],
        ];
        array_unshift($build['table']['#rows'], [
          'name' => $this->l($this->bundles[$missing]->label(), $url),
          'fieldset' => $this->bundles[$missing]->fieldset,
          'value' => '',
          'operations' => \Drupal::service('renderer')->render($link),
        ]);

      }

      // Not yet created title.
      $heading = [
        '#markup' => '<strong>Settings not yet created</strong>',
      ];
      array_unshift($build['table']['#rows'], [
        'name' => \Drupal::service('renderer')->render($heading),
        'fieldset' => '',
        'value' => '',
        'operations' => '',
      ]);
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOperations(EntityInterface $entity) {

    $build = [
      '#attributes' => ['class' => ['container-inline']],
    ];

    $operations = $this->getDefaultOperations($entity);
    $operations += $this->moduleHandler()->invokeAll('entity_operation', [$entity]);
    $this->moduleHandler->alter('entity_operation', $operations, $entity);

    uasort($operations, '\Drupal\Component\Utility\SortArray::sortByWeightElement');

    $build['operations'] = [
      '#prefix' => '<div class="align-left">',
      '#type' => 'operations',
      '#links' => $operations,
      '#suffix' => '</div>',
    ];

    // Add new operation.
    $entity_bundle = $entity->bundle();
    if (isset($this->bundles[$entity_bundle]) && $this->bundles[$entity_bundle]->multiple) {
      $url = new Url('entity.site_setting_entity.add_form', [
        'site_setting_entity_type' => $entity_bundle,
      ]);
      $build['add'] = [
        '#type' => 'link',
        '#title' => $this->t('Add another'),
        '#url' => $url,
        '#attributes' => ['class' => ['button']],
      ];

    }

    return $build;
  }

}
