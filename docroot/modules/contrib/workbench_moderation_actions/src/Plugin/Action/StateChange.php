<?php

namespace Drupal\workbench_moderation_actions\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\workbench_moderation\ModerationInformationInterface;
use Drupal\workbench_moderation\StateTransitionValidation;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Publishes a media entity.
 *
 * @Action(
 *   id = "state_change",
 *   deriver =
 *   "Drupal\workbench_moderation_actions\Plugin\Deriver\StateChangeDeriver"
 * )
 */
class StateChange extends ActionBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\workbench_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * @var \Drupal\workbench_moderation\StateTransitionValidation
   */
  protected $validation;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ModerationInformationInterface $mod_info, StateTransitionValidation $validation, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->moderationInfo = $mod_info;
    $this->validation = $validation;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration, $plugin_id, $plugin_definition,
      $container->get('workbench_moderation.moderation_information'),
      $container->get('workbench_moderation.state_transition_validation'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute(ContentEntityInterface $entity = NULL) {
    if ($entity && !$this->moderationInfo->isModeratableEntity($entity)) {
      drupal_set_message($this->t('One or more entities were skipped as they are under moderation and may not be directly published or unpublished.'));
      return;
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface $object */
    $entity = $this->loadLatestRevision($entity);

    $entity->get('moderation_state')->target_id = $this->pluginDefinition['state'];
    $violations = $entity->validate();

    if (($moderation_violations = $violations->getByField('moderation_state')) && count($moderation_violations)) {
      /** @var \Symfony\Component\Validator\ConstraintViolation $violation */
      foreach ($moderation_violations as $violation) {
        drupal_set_message($violation->getMessage(), 'error');
      }
      return;
    }
    $entity->isDefaultRevision(TRUE);
    $entity->save();
  }

  /**
   * Loads the latest revision of an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Content entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The latest revision of an entity.
   */
  protected function loadLatestRevision(ContentEntityInterface $entity) {
    $original_entity = $this->moderationInfo->getLatestRevision($entity->getEntityTypeId(), $entity->id());
    if (!$entity->isDefaultTranslation() && $original_entity->hasTranslation($entity->language()->getId())) {
      $original_entity = $original_entity->getTranslation($entity->language()->getId());
    }
    return $original_entity;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $object */
    $object = $this->loadLatestRevision($object);
    $from = $object->get('moderation_state')->target_id;
    $to = $this->pluginDefinition['state'];

    $result = AccessResult::allowedIf($this->validation->userMayTransition($from, $to, $account));

    return $return_as_object ? $result : $result->isAllowed();
  }

}
