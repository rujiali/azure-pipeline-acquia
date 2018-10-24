<?php

namespace Drupal\entity_print\Renderer;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface as CoreRendererInterface;
use Drupal\entity_print\Asset\AssetRendererInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ContentEntityRenderer extends RendererBase {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * ContentEntityRenderer constructor.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\entity_print\Asset\AssetRendererInterface $asset_renderer
   *   The asset renderer.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(CoreRendererInterface $renderer, AssetRendererInterface $asset_renderer, EventDispatcherInterface $event_dispatcher, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($renderer, $asset_renderer, $event_dispatcher);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function render(array $entities) {
    $build = [];
    foreach ($entities as $entity) {
      $render_controller = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId());
      $build[] = $render_controller->view($entity, $this->getViewMode($entity));
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function getLabel(EntityInterface $entity) {
    return $entity->label();
  }

  /**
   * Gets the view mode to use for this entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The content entity we're viewing.
   *
   * @return string
   *   The view mode machine name.
   */
  protected function getViewMode(EntityInterface $entity) {
    // We check to see if the PDF view display have been configured, if not
    // then we simply fall back to the full display.
    $view_mode = 'pdf';
    if (!$this->entityTypeManager->getStorage('entity_view_display')->load($entity->getEntityTypeId() . '.' . $entity->bundle() . '.' . $view_mode)) {
      $view_mode = 'full';
    }
    return $view_mode;
  }

}
