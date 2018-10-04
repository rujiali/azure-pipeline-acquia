<?php

namespace Drupal\graphql_views\Plugin\Deriver\Fields;

use Drupal\graphql\Utility\StringHelper;
use Drupal\graphql_views\Plugin\Deriver\ViewDeriverBase;
use Drupal\views\Views;

/**
 * Derive fields from configured views.
 */
class ViewResultListDeriver extends ViewDeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($basePluginDefinition) {
    if ($this->entityTypeManager->hasDefinition('view')) {
      $viewStorage = $this->entityTypeManager->getStorage('view');

      foreach (Views::getApplicableViews('graphql_display') as list($viewId, $displayId)) {
        /** @var \Drupal\views\ViewEntityInterface $view */
        $view = $viewStorage->load($viewId);
        if (!$type = $this->getRowResolveType($view, $displayId)) {
          continue;
        }

        /** @var \Drupal\graphql_views\Plugin\views\display\GraphQL $display */
        $display = $this->getViewDisplay($view, $displayId);

        $id = implode('-', [$viewId, $displayId, 'result', 'list']);
        $style = $this->getViewStyle($view, $displayId);
        $this->derivatives[$id] = [
          'id' => $id,
          'type' => StringHelper::listType($type),
          'parents' => [$display->getGraphQLResultName()],
          'view' => $viewId,
          'display' => $displayId,
          'uses_fields' => $style->usesFields(),
        ] + $this->getCacheMetadataDefinition($view, $display) + $basePluginDefinition;
      }
    }

    return parent::getDerivativeDefinitions($basePluginDefinition);
  }

}
