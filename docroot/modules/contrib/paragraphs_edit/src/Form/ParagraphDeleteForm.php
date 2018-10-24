<?php

namespace Drupal\paragraphs_edit\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs_edit\ParagraphFormHelperTrait;

/**
 * Provides a form for deleting a paragraph from a node.
 */
class ParagraphDeleteForm extends ContentEntityDeleteForm {
  use ParagraphFormHelperTrait;

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete @lineage?', [
      '@lineage' => $this->lineageInspector()->getLineageString($this->entity)
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDeletionMessage() {
    return $this->t('@lineage has been deleted.', [
      '@lineage' => $this->lineageInspector()->getLineageString($this->entity)
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $parent = $this->entity->getParentEntity();
    $parent_field = $this->lineageInspector()->getParentField($this->entity);
    $parent_field_item = $this->lineageInspector()->getParentFieldItem($this->entity, $parent_field);

    $parent_field->removeItem($parent_field_item->getName());

    if ($this->lineageRevisioner()->shouldCreateNewRevision($parent)) {
      $this->lineageRevisioner()->saveNewRevision($parent);
    }
    else {
      $parent->save();
    }
  }

}
