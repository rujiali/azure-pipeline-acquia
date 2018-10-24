<?php
/**
 * @file
 * Contains \Drupal\Tests\workbench_moderation_state_access\Functional\StateAccessTest.
 */

namespace Drupal\Tests\workbench_moderation_state_access\Functional;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the state access added by this module.
 *
 * @group workbench_moderation_state_access
 */
class StateAccessTest extends BrowserTestBase {
  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'workbench_moderation_state_access',
    'node',
    'options',
    'user',
    'system',
  ];

  public function testEditPermissions() {
    // Create node type.
    $node_type_id = 'test';
    $node_type = $this->createNodeType('Test', $node_type_id);

    // Create users.
    $permissions = [
      'access content',
      'edit content in the draft state',
      'edit any ' . $node_type_id . ' content',
      'view all revisions',
      'view moderation states',
    ];
    $author = $this->drupalCreateUser($permissions);
    $permissions = [
      'access content',
      'edit content in the needs_review state',
      'edit any ' . $node_type_id . ' content',
      'view all revisions',
      'view moderation states',
    ];
    $approver = $this->drupalCreateUser($permissions);

    // Create nodes.
    /** @var Node $draft_node */
    $draft_node = Node::create([
      'type' => $node_type_id,
      'title' => 'Draft node',
      'uid' => $author->id(),
    ]);
    $draft_node->moderation_state->target_id = 'draft';
    $draft_node->save();
    /** @var Node $review_node */
    $review_node = Node::create([
      'type' => $node_type_id,
      'title' => 'Review node',
      'uid' => $approver->id(),
    ]);
    $review_node->moderation_state->target_id = 'needs_review';
    $review_node->save();

    // Author can edit draft node, but not review node.
    $access_denied = 'Access denied';

    $this->drupalLogin($author);
    $this->drupalGet('node/' . $draft_node->id() . '/edit');
    $page = $this->getSession()->getPage();
    $this->assertFalse($page->hasContent($access_denied));
    $this->drupalGet('node/' . $review_node->id() . '/edit');
    $page = $this->getSession()->getPage();
    $this->assertTrue($page->hasContent($access_denied));

    $this->drupalLogin($approver);
    $this->drupalGet('node/' . $draft_node->id() . '/edit');
    $page = $this->getSession()->getPage();
    $this->assertTrue($page->hasContent($access_denied));
    $this->drupalGet('node/' . $review_node->id() . '/edit');
    $page = $this->getSession()->getPage();
    $this->assertFalse($page->hasContent($access_denied));
  }

  /**
   * Creates a new node type.
   *
   * @param string $label
   *   The human-readable label of the type to create.
   * @param string $machine_name
   *   The machine name of the type to create.
   *
   * @return NodeType
   *   The node type just created.
   */
  protected function createNodeType($label, $machine_name) {
    /** @var NodeType $node_type */
    $node_type = NodeType::create([
      'type' => $machine_name,
      'label' => $label,
    ]);
    $node_type->setThirdPartySetting('workbench_moderation', 'enabled', TRUE);
    $node_type->save();

    return $node_type;
  }
}