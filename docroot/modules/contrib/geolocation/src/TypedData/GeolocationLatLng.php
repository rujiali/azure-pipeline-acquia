<?php

namespace Drupal\geolocation\TypedData;

use Drupal\Core\TypedData\TypedData;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;

/**
 * A computed property for the LatLng format of a parent geolocation item.
 */
class GeolocationLatLng extends TypedData {

  use DependencySerializationTrait;

  /**
   * Cached processed value.
   *
   * @var string|null
   */
  protected $value = NULL;

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    if ($this->value !== NULL) {
      return $this->value;
    }
    
    // The item is our parent.
    $item = $this->getParent();

    if ($item) {
      $lat = trim($item->get('lat')->getValue());
      $lng = trim($item->get('lng')->getValue());

      // Ensure latitude and longitude exist.
      if ($lat !== NULL && $lng !== NULL) {
        // Format the returned value.
        $this->value = $lat . ', ' . $lng;
      }
    }
    return $this->value;
  }
}