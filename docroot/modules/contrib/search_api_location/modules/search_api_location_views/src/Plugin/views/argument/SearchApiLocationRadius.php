<?php

namespace Drupal\search_api_location_views\Plugin\views\argument;

use Drupal\search_api\Plugin\views\SearchApiHandlerTrait;
use Drupal\views\Plugin\views\argument\ArgumentPluginBase;

/**
 * Provides a contextual filter for defining a location filter radius.
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("search_api_location_radius")
 */
class SearchApiLocationRadius extends ArgumentPluginBase {

  use SearchApiHandlerTrait;
  use SearchApiLocationArgumentTrait;

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE) {
    // Must be single and must be a decimal.
    if (is_numeric($this->argument) && $this->argument > 0) {
      $query = $this->getQuery();
      $location_options = (array) $query->getOption('search_api_location');
      $add_options = [
        'radius' => $this->argument,
      ];
      $this->addFieldOptions($location_options, $add_options, $this->realField);
      $query->setOption('search_api_location', $location_options);
    }
  }

}
