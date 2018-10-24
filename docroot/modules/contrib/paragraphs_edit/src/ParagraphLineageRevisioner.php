<?php

namespace Drupal\paragraphs_edit;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableEntityBundleInterface;
use Drupal\paragraphs\ParagraphInterface;

class ParagraphLineageRevisioner {

  protected $lineageInspector;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Construct a new ParagraphLineageRevisioner object.
   *
   * @param \Drupal\paragraphs_edit\ParagraphLineageInspector $lineage_inspector
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(ParagraphLineageInspector $lineage_inspector, EntityTypeManagerInterface $entity_type_manager) {
    $this->lineageInspector = $lineage_inspector;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Saves all of given entity's lineage as new revisions.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity whose lineage to save as new revisions.
   *
   * @return int
   *   Either SAVED_NEW or SAVED_UPDATED, depending on the operation performed.
   */
  public function saveNewRevision(ContentEntityInterface $entity) {
    $result = $this->doSaveNewRevision($entity);

    while ($entity instanceof ParagraphInterface) {
      $parent = $entity->getParentEntity();
      if (!$parent) {
        break;
      }

      $this->doSaveNewRevision($parent);

      $entity = $parent;
    }

    return $result;
  }

  /**
   * Saves an entity as a new revision.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to save.
   *
   * @return int
   *   Either SAVED_NEW or SAVED_UPDATED, depending on the operation performed.
   */
  protected function doSaveNewRevision(ContentEntityInterface $entity) {
    // Get the parent field item before saving, as after saving the
    // revision ID will be changed.
    if ($entity instanceof ParagraphInterface) {
      $parent_field_item = $this->lineageInspector->getParentFieldItem($entity);
    }

    try {
      $entity->setNewRevision();
    }
    catch (\LogicException $e) {
      // A content entity not necessarily supports revisioning.
    }

    $status = $entity->save();

    if (isset($parent_field_item)) {
      $parent_field_item->set('target_revision_id', $entity->getRevisionId());
    }

    return $status;
  }

  /**
   * Checks if a given entity should be saved as a new revision by default.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check for default new revision.
   *
   * @return bool
   *   TRUE if this entity is set to be saved as a new revision by default,
   *   FALSE otherwise.
   */
  public function shouldCreateNewRevision(EntityInterface $entity) {
    $new_revision_default = FALSE;

    if ($bundle_entity_type = $entity->getEntityType()->getBundleEntityType()) {
      $bundle_entity = $this->entityTypeManager
        ->getStorage($bundle_entity_type)
        ->load($entity->bundle());

      if ($bundle_entity instanceof RevisionableEntityBundleInterface) {
        $new_revision_default = $bundle_entity->shouldCreateNewRevision();
      }
    }

    return $new_revision_default;
  }

}
