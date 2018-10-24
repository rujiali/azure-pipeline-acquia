<?php

namespace Drupal\Tests\simple_oauth\Functional;

/**
 * Trait with methods needed by tests.
 */
trait SimpleOauthTestTrait {

  /**
   * Set up public and private keys.
   */
  public function setUpKeys() {
    $path = $this->container->get('module_handler')->getModule('simple_oauth')->getPath();

    $public_key_path = 'private:///public.key';
    $private_key_path = 'private:///private.key';

    $source_public_key_path = '/' . $path . '/tests/certificates/public.key';
    $source_private_key_path = '/' . $path . '/tests/certificates/private.key';
    file_put_contents($public_key_path, file_get_contents(DRUPAL_ROOT . $source_public_key_path));
    file_put_contents($private_key_path, file_get_contents(DRUPAL_ROOT . $source_private_key_path));
    chmod($public_key_path, 0660);
    chmod($private_key_path, 0660);

    /** @var \Drupal\Core\File\FileSystemInterface $filesystem */
    $filesystem = \Drupal::service('file_system');

    $settings = $this->config('simple_oauth.settings');
    $settings->set('public_key', $filesystem->realpath($public_key_path));
    $settings->set('private_key', $filesystem->realpath($private_key_path));
    $settings->save();

  }

}
