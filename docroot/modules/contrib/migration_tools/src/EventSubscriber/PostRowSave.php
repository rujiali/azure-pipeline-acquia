<?php

namespace Drupal\migration_tools\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Event\MigrateEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Modify raw data on import.
 */
class PostRowSave implements EventSubscriberInterface {
  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\migrate_plus\Plugin\MigrationConfigEntityPluginManager definition.
   *
   * @var \Drupal\migrate_plus\Plugin\MigrationConfigEntityPluginManager
   */
  protected $migrationConfigEntityPluginManager;

  /**
   * The URL of the document to retrieve.
   *
   * @var string
   */
  protected $url;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::POST_ROW_SAVE] = 'onMigratePostRowSave';
    return $events;
  }

  /**
   * Callback function for prepare row migration event.
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *   The prepare row event.
   *
   * @throws \Drupal\migrate\MigrateSkipRowException
   */
  public function onMigratePostRowSave(MigratePostRowSaveEvent $event) {
    // $migration = $event->getMigration();
    // $row = $event->getRow();
    // $nids = $event->getDestinationIdValues();
  }

}
