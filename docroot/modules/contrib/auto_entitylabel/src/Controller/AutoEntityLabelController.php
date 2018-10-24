<?php

namespace Drupal\auto_entitylabel\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * AutoEntityLabelController class.
 */
class AutoEntityLabelController extends ControllerBase {

  /**
   * Entity type storage interface.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   *   Node type storage.
   */
  protected $nodeTypeStorage;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $node_type_storage
   *   Node types storage.
   */
  public function __construct(EntityStorageInterface $node_type_storage) {
    $this->nodeTypeStorage = $node_type_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $entity_manager = $container->get('entity.manager');
    return new static($entity_manager->getStorage('node_type'));
  }

  /**
   * Get Content Type Items.
   */
  public function getContentTypesItems() {

    $markup = '<ul class="admin-list">';

    $all_content_types = $this->nodeTypeStorage->loadMultiple();

    foreach ($all_content_types as $content_type_machine_name => $content_type) {

      $content_type_label = $content_type->label();

      $markup .= '<li><a href="/admin/structure/types/manage/' . $content_type_machine_name . '/auto-label"><span class="label">' . $content_type_label . '</span><div class="description">AUTOMATIC LABEL GENERATION FOR ' . strtoupper($content_type_label) . '</div></a></li>';
    }

    $markup .= '</ul>';

    $element = [
      '#markup' => $markup,
    ];
    return $element;
  }

}
