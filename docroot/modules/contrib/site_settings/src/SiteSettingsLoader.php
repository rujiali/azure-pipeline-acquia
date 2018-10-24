<?php

namespace Drupal\site_settings;

use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Class SiteSettingsLoader.
 *
 * @package Drupal\site_settings
 */
class SiteSettingsLoader {

  /**
   * Drupal\Core\Entity\Query\QueryFactory definition.
   *
   * @var Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQuery;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Language\LanguageManagerInterface definition.
   *
   * @var Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Variable to store the loaded settings.
   *
   * @var array
   */
  protected $settings;

  /**
   * Constructor.
   */
  public function __construct(QueryFactory $entity_query, EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager) {
    $this->entityQuery = $entity_query;
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * Load site settings by fieldset.
   *
   * @param string $fieldset
   *   The name of the fieldset.
   *
   * @return array
   *   All settings within the given fieldset.
   */
  public function loadByFieldset($fieldset) {
    $this->loadAll();
    $fieldset = $this->fieldsetKey($fieldset);
    return $this->settings[$fieldset];
  }

  /**
   * Load site settings by fieldset.
   *
   * @param bool $rebuild_cache
   *   Force rebuilding of the cache by setting to true.
   *
   * @return array
   *   All settings.
   */
  public function loadAll($rebuild_cache = FALSE) {
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    if (!$rebuild_cache && $cache = \Drupal::cache(SITE_SETTINGS_CACHE_BIN)->get(SITE_SETTINGS_CACHE_CID . ':' . $langcode)) {
      $this->settings = $cache->data;
    }
    else {
      $this->rebuildCache($langcode);
    }
    return $this->settings;
  }

  /**
   * Rebuild the cache.
   */
  public function rebuildCache($langcode) {
    $this->buildSettings($langcode);
    \Drupal::cache(SITE_SETTINGS_CACHE_BIN)->set(SITE_SETTINGS_CACHE_CID . ':' . $langcode, $this->settings);
  }

  /**
   * Clear the cache.
   */
  public function clearCache() {
    \Drupal::cache(SITE_SETTINGS_CACHE_BIN)->deleteAll();
  }

  /**
   * Build the settings array.
   */
  private function buildSettings($langcode) {

    // Clear the existing settings to avoid empty keys.
    $this->settings = [];

    // Get all site settings.
    $query = $this->entityQuery->get('site_setting_entity');
    if ($ids = $query->execute()) {

      // Get the settings.
      $setting_entities = $this->entityTypeManager
        ->getStorage('site_setting_entity')
        ->loadMultiple($ids);

      // Get entity type configurations at once for performance.
      $entities = [];
      $entity_type = \Drupal::entityTypeManager()->getStorage('site_setting_entity_type');
      if ($entity_type) {
        $entities = $entity_type->loadMultiple();
      }

      foreach ($setting_entities as $entity) {
        if ($entity->hasTranslation($langcode)) {
          $entity = $entity->getTranslation($langcode);
        }

        // Get data.
        $fieldset = $entity->fieldset->getValue()[0]['value'];
        $fieldset = $this->fieldsetKey($fieldset);
        $type = $entity->type->getValue()[0]['target_id'];
        $multiple = (isset($entities[$type]) ? $entities[$type]->multiple : FALSE);

        // If we have multiple, set as array of entities.
        if ($multiple) {
          if (!isset($this->settings[$fieldset][$type])) {
            $this->settings[$fieldset][$type] = [];
          }
          $this->settings[$fieldset][$type][] = $this->getValues($entity);
        }
        else {
          $this->settings[$fieldset][$type] = $this->getValues($entity);
        }
      }

      // Get all possibilities and fill with empty values.
      $bundles = $this->entityTypeManager
        ->getStorage('site_setting_entity_type')
        ->loadMultiple();
      foreach ($bundles as $bundle) {
        $fieldset = $this->fieldsetKey($bundle->fieldset);
        $id = $bundle->id();

        // Only fill if not yet set.
        if (!isset($this->settings[$fieldset][$id])) {
          $this->settings[$fieldset][$id] = '';
        }
      }
    }
  }

  /**
   * Get the values from the entity and return in as simple an array possible.
   *
   * @param object $entity
   *   Field Entity.
   *
   * @return mixed
   *   The values.
   */
  private function getValues($entity) {
    $build = [];
    $fields = $entity->getFields();
    foreach ($fields as $key => $field) {
      $field_definition = $field->getFieldDefinition();

      // Exclude fields on the object that are base config.
      if (!method_exists(get_class($field_definition), 'isBaseField') || !$field_definition->isBaseField()) {

        if ($value = $this->getValue($field_definition, $field)) {
          $build[$key] = $value;

          // Add supplementary data to some field types.
          switch ($field_definition->getType()) {
            case 'image':
            case 'file':
              $build[$key] = $this->addSupplementaryImageData($build[$key], $field);
              break;
          }
        }
      }
    }
    return count($build) > 1 ? $build : reset($build);
  }

  /**
   * Get the value for the particular field item.
   *
   * @param object $field_definition
   *   The field definition.
   * @param object $field
   *   The field object.
   *
   * @return bool|array
   *   The value or false.
   */
  private function getValue($field_definition, $field) {
    if ($value = $field->getValue()) {

      // Store the values in as flat a way as possible based on what is set.
      if (count($value) <= 1) {
        $item = reset($value);
        if (count($item) <= 1) {
          return reset($item);
        }
        else {
          return $item;
        }
      }
      else {
        return $value;
      }
    }
    return FALSE;
  }

  /**
   * Add supplementary image data to the site settings.
   *
   * @param array $data
   *   The existing data.
   * @param object $field
   *   The field object.
   *
   * @return array
   *   The data with the new supplementary information included.
   */
  private function addSupplementaryImageData(array $data, $field) {
    if ($entities = $field->referencedEntities()) {
      if (count($entities) > 1) {

        // If multiple images add data to each.
        foreach ($data as $key => $sub_image_data) {
          $data[$key]['filename'] = $entities[$key]->getFilename();
          $data[$key]['uri'] = $entities[$key]->getFileUri();
          $data[$key]['mime_type'] = $entities[$key]->getMimeType();
          $data[$key]['size'] = $entities[$key]->getSize();
          $data[$key]['is_permanent'] = $entities[$key]->isPermanent();
        }
      }
      else {

        // Add the entity to the image.
        $entity = reset($entities);
        $data['filename'] = $entity->getFilename();
        $data['uri'] = $entity->getFileUri();
        $data['mime_type'] = $entity->getMimeType();
        $data['size'] = $entity->getSize();
        $data['is_permanent'] = $entity->isPermanent();
      }
    }
    return $data;
  }

  /**
   * Create a lowercase key with no spaces from the fieldset label.
   *
   * @param string $fieldset
   *   The fieldset key.
   */
  private function fieldsetKey($fieldset) {
    return strtolower(str_replace(' ', '_', $fieldset));
  }

}
