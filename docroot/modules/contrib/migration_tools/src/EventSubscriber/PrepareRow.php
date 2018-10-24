<?php

namespace Drupal\migration_tools\EventSubscriber;

use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate_plus\Event\MigrateEvents;
use Drupal\migrate_plus\Event\MigratePrepareRowEvent;
use Drupal\migration_tools\Message;
use Drupal\migration_tools\Modifier\DomModifier;
use Drupal\migration_tools\Modifier\SourceModifierHtml;
use Drupal\migration_tools\Obtainer\Job;
use Drupal\migration_tools\Operations;
use Drupal\migration_tools\SourceParser\HtmlBase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Modify raw data on import.
 */
class PrepareRow implements EventSubscriberInterface {

  /**
   * The URL of the document to retrieve.
   *
   * @var string
   */
  protected $url;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::PREPARE_ROW] = 'onMigratePrepareRow';
    return $events;
  }

  /**
   * Callback function for prepare row migration event.
   *
   * @param \Drupal\migrate_plus\Event\MigratePrepareRowEvent $event
   *   The prepare row event.
   */
  public function onMigratePrepareRow(MigratePrepareRowEvent $event) {
    $row = $event->getRow();

    $migration_tools_settings = $row->getSourceProperty('migration_tools');
    if (!empty($migration_tools_settings)) {
      Operations::process($migration_tools_settings, $row);
    }
  }

}
