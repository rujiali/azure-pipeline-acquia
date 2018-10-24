<?php

namespace Drupal\paragraphs_edit;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a helper for paragraph forms.
 *
 * @property \Drupal\paragraphs\ParagraphInterface $entity
 */
trait ParagraphFormHelperTrait {

  private $lineageInspector;

  private $lineageRevisioner;

  protected $rootParent;

  /**
   * Returns the lineage inspector service.
   *
   * @return \Drupal\paragraphs_edit\ParagraphLineageInspector
   */
  protected function lineageInspector() {
    if (!isset($this->lineageInspector)) {
      $this->lineageInspector = \Drupal::service('paragraphs_edit.lineage.inspector');
    }
    return $this->lineageInspector;
  }

  /**
   * Returns the lineage revisioner service.
   *
   * @return \Drupal\paragraphs_edit\ParagraphLineageRevisioner|null
   */
  protected function lineageRevisioner() {
    if (!isset($this->lineageRevisioner)) {
      $this->lineageRevisioner = \Drupal::service('paragraphs_edit.lineage.revisioner');
    }
    return $this->lineageRevisioner;
  }

  /**
   * {@inheritdoc}
   *
   * Overridden to store the root parent entity.
   */
  public function buildForm(array $form, FormStateInterface $form_state, EntityInterface $root_parent = NULL) {
    $this->rootParent = $root_parent;

    return parent::buildForm($form, $form_state);
  }
}
