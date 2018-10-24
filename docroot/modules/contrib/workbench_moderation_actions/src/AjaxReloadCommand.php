<?php

namespace Drupal\workbench_moderation_actions;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Tells the client to perform an AJAX reload.
 */
class AjaxReloadCommand implements CommandInterface {

  /**
   * Return an array to be run through json_encode and sent to the client.
   */
  public function render() {
    return [
      'command' => 'reload',
    ];
  }

}
