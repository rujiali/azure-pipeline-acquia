<?php

namespace Drupal\graphql_views\Plugin\GraphQL\InputTypes;

use Drupal\graphql\Plugin\GraphQL\InputTypes\InputTypePluginBase;

/**
 * Creates input types for entity mutations.
 *
 * @GraphQLInputType(
 *   id = "view_filter_input",
 *   provider = "views",
 *   deriver = "Drupal\graphql_views\Plugin\Deriver\InputTypes\ViewFilterInputDeriver"
 * )
 */
class ViewFilterInput extends InputTypePluginBase {

}
