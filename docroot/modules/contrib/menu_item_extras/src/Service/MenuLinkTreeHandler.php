<?php

namespace Drupal\menu_item_extras\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\menu_link_content\MenuLinkContentInterface;

/**
 * Class MenuLinkTreeHandler.
 */
class MenuLinkTreeHandler implements MenuLinkTreeHandlerInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Constructs a new MenuLinkTreeHandler.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $entity_repository) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function getMenuLinkItemEntity(MenuLinkInterface $link) {
    $menu_item = NULL;
    $metadata = $link->getMetaData();
    if (!empty($metadata['entity_id'])) {
      /** @var \Drupal\menu_link_content\Entity\MenuLinkContent $menu_item */
      $menu_item = $this->entityTypeManager
        ->getStorage('menu_link_content')
        ->load($metadata['entity_id']);
    }
    else {
      $menu_item = $this->entityTypeManager
        ->getStorage('menu_link_content')
        ->create($link->getPluginDefinition());
    }
    if ($menu_item) {
      $menu_item = $this->entityRepository->getTranslationFromContext($menu_item);
    }
    return $menu_item;
  }

  /**
   * {@inheritdoc}
   */
  public function getMenuLinkContentViewMode(MenuLinkContentInterface $entity) {
    $view_mode = 'default';
    if (!$entity->get('view_mode')->isEmpty()) {
      $value = $entity->get('view_mode')->first()->getValue();
      if (!empty($value['value'])) {
        $view_mode = $value['value'];
      }
    }
    return $view_mode;
  }

  /**
   * {@inheritdoc}
   */
  public function getMenuLinkItemContent(MenuLinkContentInterface $entity, $menu_level = NULL, $show_item_link = FALSE) {
    $view_builder = $this->entityTypeManager
      ->getViewBuilder('menu_link_content');
    if ($entity->id()) {
      $view_mode = $this->getMenuLinkContentViewMode($entity);
    }
    else {
      $view_mode = 'default';
    }
    $render_output = $view_builder->view($entity, $view_mode);
    $cached_context = [
      'languages',
      'theme',
      'url.path',
      'url.query_args',
      'user',
    ];
    $render_output['#cache']['contexts'] = array_merge($cached_context, $render_output['#cache']['contexts']);
    $render_output['#show_item_link'] = $show_item_link;

    if (!is_null($menu_level)) {
      $render_output['#menu_level'] = $menu_level;
    }

    return $render_output;
  }

  /**
   * {@inheritdoc}
   */
  public function getMenuLinkItemViewMode(MenuLinkInterface $link) {
    $entity = $this->getMenuLinkItemEntity($link);
    if ($entity) {
      return $this->getMenuLinkContentViewMode($entity);
    }

    return 'default';
  }

  /**
   * {@inheritdoc}
   */
  public function isMenuLinkDisplayedChildren(MenuLinkInterface $link) {
    /** @var \Drupal\menu_link_content\Entity\MenuLinkContent $menu_item */
    $entity = $this->getMenuLinkItemEntity($link);
    if ($entity) {
      $view_mode = $this->getMenuLinkContentViewMode($entity);
      /* @var \Drupal\Core\Entity\Entity\EntityViewDisplay $display */
      $display = $this->entityTypeManager
        ->getStorage('entity_view_display')
        ->load($entity->getEntityTypeId() . '.' . $entity->bundle() . '.' . $view_mode);
      if ($display->getComponent('children')) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function processMenuLinkTree(array &$items, $menu_level = -1, $show_item_link = FALSE) {
    $menu_level++;
    foreach ($items as &$item) {
      $content = [];
      if (isset($item['original_link'])) {
        $content['#item'] = $item;
        $content['entity'] = $this->getMenuLinkItemEntity($item['original_link']);
        $content['content'] = $content['entity'] ? $this->getMenuLinkItemContent($content['entity'], $menu_level, $show_item_link) : NULL;
        $content['menu_level'] = $menu_level;
      }
      // Process subitems.
      if ($item['below']) {
        $content['content']['children'] = $this->processMenuLinkTree($item['below'], $menu_level, $show_item_link);
      }
      $item = array_merge($item, $content);
    }
    return $items;
  }

}
