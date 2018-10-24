<?php

namespace Drupal\metatag\Normalizer;

use Drupal\jsonapi\Normalizer\Value\FieldItemNormalizerValue;
use Drupal\jsonapi\Normalizer\Value\FieldNormalizerValue;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * Converts the Metatag field item object structure to Metatag array structure.
 */
class MetatagJsonApiNormalizer extends MetatagNormalizer {

  /**
   * {@inheritdoc}
   */
  protected $format = ['api_json'];

  /**
   * {@inheritdoc}
   */
  public function normalize($field_item, $format = NULL, array $context = []) {
    /* @var \Drupal\metatag\Plugin\Field\MetatagEntityFieldItemList $field_item */

    $normalized = parent::normalize($field_item, $format, $context);

    $access = $field_item->access('view', $context['account'], TRUE);
    $property_type = static::isRelationship($field_item) ? 'relationships' : 'attributes';

    // @todo Use the constant \Drupal\serialization\Normalizer\CacheableNormalizerInterface::SERIALIZATION_CONTEXT_CACHEABILITY instead of the 'cacheability' string when JSON API requires Drupal 8.5 or newer.
    $field_item_value = new FieldItemNormalizerValue($normalized['value'], new CacheableMetadata());

    return new FieldNormalizerValue($access, [$field_item_value], 1, $property_type);
  }

  /**
   * Checks if the passed field is a relationship field.
   *
   * @param mixed $field
   *   The field.
   *
   * @return bool
   *   TRUE if it's a JSON API relationship.
   */
  protected static function isRelationship($field) {
    return $field instanceof EntityReferenceFieldItemList || $field instanceof Relationship;
  }
}
