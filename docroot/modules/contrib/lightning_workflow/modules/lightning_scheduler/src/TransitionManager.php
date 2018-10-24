<?php

namespace Drupal\lightning_scheduler;

use Drupal\Component\Serialization\Json;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

class TransitionManager {

  use StringTranslationTrait;

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInformation;

  /**
   * The currently logged-in user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * TransitionManager constructor.
   *
   * @param ModerationInformationInterface $moderation_information
   *   The moderation information service.
   * @param AccountInterface $current_user
   *   The currently logged-in user.
   * @param EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param LoggerChannelInterface $logger
   *   The logger channel.
   * @param TranslationInterface $translation
   *   (optional) The string translation service.
   */
  public function __construct(ModerationInformationInterface $moderation_information, AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager, LoggerChannelInterface $logger, TranslationInterface $translation = NULL) {
    $this->moderationInformation = $moderation_information;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;

    if ($translation) {
      $this->setStringTranslation($translation);
    }
  }

  /**
   * Returns an array of available workflow states for an entity.
   *
   * A workflow state is considered "available" if the current user has
   * permission to use or schedule it.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity which has the workflow.
   *
   * @return array
   *   An associative array where the keys are the workflow state IDs, and the
   *   values are the states' human-readable labels.
   */
  public function getStates(ContentEntityInterface $entity) {
    $states = [];

    $workflow = $this->moderationInformation->getWorkflowForEntity($entity);

    foreach ($workflow->getTypePlugin()->getTransitions() as $transition) {
      $base_permission = $workflow->id() . ' transition ' . $transition->id();

      if ($this->currentUser->hasPermission("schedule $base_permission") || $this->currentUser->hasPermission("use $base_permission")) {
        $to_state = $transition->to();
        $states[ $to_state->id() ] = $to_state->label();
      }
    }
    return $states;
  }

  /**
   * Validates incoming transition data.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @see lightning_scheduler_form_alter()
   */
  public static function validate(array $element, FormStateInterface $form_state) {
    $data = Json::decode($element['#value']);

    if (json_last_error() !== JSON_ERROR_NONE) {
      $variables = [
        '%error' => json_last_error_msg(),
      ];
      $form_state->setError($element, t('Invalid transition data: %error', $variables));
      return;
    }

    if (! is_array($data)) {
      $form_state->setError($element, t('Expected scheduled transitions to be an array.'));
      return;
    }

    $minimum_date = NULL;
    $dates = $states = [];
    $format_options = [
      'timezone' => drupal_get_user_timezone(),
    ];

    foreach ($data as $transition) {
      if (empty($transition['when'])) {
        $form_state->setError($element, t('Scheduled transitions must have a date and time.'));
        return;
      }

      $date_time = new DrupalDateTime($transition['when'], 'UTC');
      if ($date_time->hasErrors()) {
        $variables = [
          '%when' => $transition['when'],
        ];
        $form_state->setError($element, t('"%when" is not a valid date and time.', $variables));
        return;
      }

      // The transition must take place after $minimum_date.
      if ($minimum_date instanceof DrupalDateTime && static::toInt($date_time) < static::toInt($minimum_date)) {
        $variables = [
          '@date' => $minimum_date->format('F j, Y', $format_options),
          '@time' => $minimum_date->format('g:i A', $format_options),
        ];
        $form_state->setError($element, t('You cannot schedule a transition to take place before @time on @date.', $variables));
        return;
      }
      $minimum_date = $date_time;

      // Prepare field values for the date and state.
      array_push($dates, [
        'value' => $date_time->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ]);
      array_push($states, [
        'value' => $transition['state'],
      ]);
    }

    $form_object = $form_state->getFormObject();

    // This check is done explicitly in order to facilitate unit testing.
    // @see \Drupal\Tests\lightning_scheduler\Kernel\TransitionManagerTest
    if ($form_object instanceof EntityFormInterface) {
      $form_object->getEntity()
        ->set('scheduled_transition_state', $states)
        ->set('scheduled_transition_date', $dates);
    }
  }

  /**
   * Executes all scheduled transitions for a particular entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param DrupalDateTime $now
   *   The time that processing began.
   */
  public function process($entity_type_id, DrupalDateTime $now) {
    /** @var ContentEntityInterface $entity */
    foreach ($this->getTransitionable($entity_type_id, $now) as $entity) {
      $error_context = [
        'entity_type' => (string) $entity->getEntityType()->getSingularLabel(),
        'entity' => $entity->label(),
      ];

      $workflow = $this->moderationInformation->getWorkflowForEntity($entity);
      // If the entity hasn't got a workflow, what are we doing here?
      if (empty($workflow)) {
        $message = $this->t('Could not execute scheduled transition(s) for {entity_type} "{entity}" because no workflow is assigned to it.');
        $this->logger->error($message, $error_context);
        continue;
      }

      $transition_set = new TransitionSet(
        $entity->get('scheduled_transition_date'),
        $entity->get('scheduled_transition_state')
      );

      $to_state = $transition_set->getExpectedState($now);
      // If no workflow state is targeted, there's nothing to transition to.
      if (empty($to_state)) {
        continue;
      }

      $from_state = $entity->moderation_state->value;
      $plugin = $workflow->getTypePlugin();

      if ($plugin->hasTransitionFromStateToState($from_state, $to_state)) {
        $entity->set('moderation_state', $to_state)->save();
      }
      else {
        $error_context += [
          'from_state' => $plugin->getState($from_state)->label(),
          'to_state' => $plugin->getState($to_state)->label(),
          'workflow' => $workflow->label(),
        ];
        $message = $this->t('Could not transition {entity_type} "{entity}" from {from_state} to {to_state} because no such transition exists in the "{workflow}" workflow.');
        $this->logger->warning($message, $error_context);
      }
    }
  }

  /**
   * Returns all transitionable entities of a given type.
   *
   * The entity type is assumed to have the scheduled_transition_date field.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param DrupalDateTime $now
   *   The time that processing began.
   *
   * @return \Generator
   *   An iterable of the latest revisions of all transitionable entities of the
   *   given type.
   */
  protected function getTransitionable($entity_type_id, DrupalDateTime $now) {
    $storage = $this->entityTypeManager->getStorage($entity_type_id);

    $now = $now->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    // Entities are transitionable if its latest revision has any transitions
    // scheduled now or in the past.
    $IDs = $storage->getQuery()
      ->latestRevision()
      ->accessCheck(FALSE)
      ->condition('scheduled_transition_date.value', $now, '<=')
      ->execute();

    foreach (array_keys($IDs) as $revision_id) {
      yield $storage->loadRevision($revision_id);
    }
  }

  /**
   * Represents a DrupalDateTime as an integer.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $when
   *   The date and time to convert.
   *
   * @return int
   *   The date and time, in the UTC time zone, in the format YYYYMMDDHHMMSS
   *   (i.e., a comparable integer).
   */
  protected static function toInt(DrupalDateTime $when) {
    return (int) $when->format('YmdHis');
  }

}
