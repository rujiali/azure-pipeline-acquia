<?php

namespace Drupal\graphql_core\Plugin\GraphQL\Fields\Languages;

use Drupal\Core\Language\LanguageInterface;
use Drupal\graphql\GraphQL\Execution\ResolveContext;
use Drupal\graphql\Plugin\GraphQL\Fields\FieldPluginBase;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * Retrieve a language's id.
 *
 * @GraphQLField(
 *   id = "language_id",
 *   secure = true,
 *   name = "id",
 *   description = @Translation("The language id."),
 *   type = "String",
 *   parents = {"Language"}
 * )
 */
class LanguageId extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function resolveValues($value, array $args, ResolveContext $context, ResolveInfo $info) {
    if ($value instanceof LanguageInterface) {
      yield $value->getId();
    }
  }

}
