<?php

namespace Drupal\Tests\workbench_moderation_actions\Kernel;

/**
 * @file
 * Tests.
 */

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\workbench_moderation_actions\Plugin\Action\StateChange;

/**
 * Tests.
 *
 * @group workbench_moderation_actions
 *
 * @see \Drupal\workbench_moderation_actions\Plugin\Deriver\StateChangeDeriver
 */
class DeriverTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'workbench_moderation',
    'workbench_moderation_actions',
    'node',
    'user',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installConfig('workbench_moderation');
  }

  /**
   * Checks the correct actions are available.
   */
  public function testAvailableActionPlugins() {
    $bundle = NodeType::create([
      'type' => 'test',
    ]);
    $bundle->setThirdPartySetting('workbench_moderation', 'enabled', TRUE);
    $bundle->setThirdPartySetting('workbench_moderation', 'allowed_moderation_states', [
      'archive',
      'published',
      'draft',
      'review',
    ]);
    $bundle->save();

    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');

    $definitions = $action_manager->getDefinitions();
    $definitions_wb = array_filter($definitions, function (array $definition) {
      return $definition['provider'] === 'workbench_moderation_actions';
    });
    $this->assertCount(4, $definitions_wb);
    $this->assertArrayHasKey('state_change:node__archived', $definitions_wb);
    $this->assertEquals(StateChange::class, $definitions_wb['state_change:node__archived']['class']);
    $this->assertArrayHasKey('state_change:node__draft', $definitions_wb);
    $this->assertEquals(StateChange::class, $definitions_wb['state_change:node__draft']['class']);
    $this->assertArrayHasKey('state_change:node__needs_review', $definitions_wb);
    $this->assertEquals(StateChange::class, $definitions_wb['state_change:node__needs_review']['class']);
    $this->assertArrayHasKey('state_change:node__published', $definitions_wb);
    $this->assertEquals(StateChange::class, $definitions_wb['state_change:node__published']['class']);
  }

}
