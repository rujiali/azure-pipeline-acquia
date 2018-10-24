<?php

namespace Drupal\Tests\lightning_scheduler\Functional;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\node\NodeInterface;

/**
 * @group lightning
 * @group lightning_workflow
 * @group lightning_scheduler
 */
class BaseFieldMigrationTest extends MigrationTestBase {

  public function test() {
    parent::test();

    $assert = $this->assertSession();
    $assert->pageTextContains('You are about to migrate scheduled transitions for all custom block entities and content items.');
    $assert->elementExists('named', ['link', 'switch to maintenance mode']);
    $assert->buttonExists('Continue')->press();
    $this->checkForMetaRefresh();

    $assert->pageTextContains('All migrations are completed.');
    $assert->pageTextNotContains('You are about to migrate scheduled transitions');
    $assert->buttonNotExists('Continue');
    $assert->buttonNotExists('Cancel');

    $storage = $this->postMigration('node');

    /** @var NodeInterface $node */
    $node = $storage->load(1);
    $this->assertInstanceOf(NodeInterface::class, $node);
    $this->assertNode($node, '2018-09-19 08:57', 'published');
    $this->assertTrue($node->hasTranslation('fr'));
    $this->assertNode($node->getTranslation('fr'), '2018-09-04 20:15', 'published');

    // Test the default revision, loaded explicitly.
    $node = $storage->loadRevision(5);
    $this->assertInstanceOf(NodeInterface::class, $node);
    $this->assertNode($node, '2018-09-19 08:57', 'published');
    $this->assertTrue($node->hasTranslation('fr'));
    $this->assertNode($node->getTranslation('fr'), '2018-09-04 20:15', 'published');

    // Test previous revisions too.
    $node = $storage->loadRevision(4);
    $this->assertInstanceOf(NodeInterface::class, $node);
    $this->assertNode($node, '2018-09-19 08:57', 'published');
    $this->assertTrue($node->hasTranslation('fr'));
    $this->assertNode($node->getTranslation('fr'), '2018-11-05 02:30', 'published');

    $node = $storage->loadRevision(3);
    $this->assertInstanceOf(NodeInterface::class, $node);
    $this->assertNode($node, '2018-09-19 08:57', 'published');
    $this->assertFalse($node->hasTranslation('fr'));

    $node = $storage->loadRevision(2);
    $this->assertInstanceOf(NodeInterface::class, $node);
    $this->assertNode($node, '2018-09-05 17:00', 'published');
    $this->assertFalse($node->hasTranslation('fr'));

    $node = $storage->loadRevision(1);
    $this->assertInstanceOf(NodeInterface::class, $node);
    $this->assertTrue($node->get('scheduled_transition_date')->isEmpty());
    $this->assertTrue($node->get('scheduled_transition_state')->isEmpty());
    $this->assertFalse($node->hasTranslation('fr'));
  }

  protected function assertNode(NodeInterface $node, $expected_date, $expected_state) {
    $this->assertFalse($node->hasField('scheduled_publication'));
    $this->assertFalse($node->hasField('scheduled_moderation_state'));

    $this->assertTrue($node->hasField('scheduled_transition_date'));
    $this->assertTrue($node->hasField('scheduled_transition_state'));

    $date = $node->get('scheduled_transition_date');
    $state = $node->get('scheduled_transition_state');

    $this->assertSame(
      FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      $date
        ->getFieldDefinition()
        ->getFieldStorageDefinition()
        ->getCardinality()
    );
    $this->assertSame(
      FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      $state
        ->getFieldDefinition()
        ->getFieldStorageDefinition()
        ->getCardinality()
    );

    $this->assertFalse($date->isEmpty());
    $this->assertFalse($state->isEmpty());

    $date_options = [
      'timezone' => 'America/New_York',
    ];
    $this->assertSame($expected_date, $date->date->format('Y-m-d H:i', $date_options));
    $this->assertSame($expected_state, $state->value);
  }

}
