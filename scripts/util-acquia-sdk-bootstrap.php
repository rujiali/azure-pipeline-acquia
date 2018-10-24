<?php

/**
 * @file
 * Simple bootstrap of typhonius/acquia-php-sdk-v2 which assumes you're
 * running a script from the project root.
 *
 * After including this script you will have in scope:
 *   $cloudapi \AcquiaCloudApi\CloudApi\Client
 *     Allows you to
 *   $project \stdClass
 *     Variables about Acquia stored in composer.json config "extra" section.
 */

require 'vendor/autoload.php';

use AcquiaCloudApi\CloudApi\Client;
use AcquiaCloudApi\CloudApi\Connector;
use Consolidation\Config\Loader\YamlConfigLoader;

// A variety of Acquia info is stored in the composer.json extras section.
$project = json_decode(exec('composer config extra.acquia'));

// Acquia credentials (key/secret) come from an encrypted YAML file.
$credentials_file = 'acquiacli.yml';
if (!file_exists($credentials_file)) {
  print "Acquia credentials have not been unencrypted in this environment, \nwith the password from TeamPassword, please run:\n
    ansible-vault --ask-vault-pass view .drupal-vm/assets/acquiacli.yml > acquiacli.yml\n\n";
  exit(1);
}
$loader = new YamlConfigLoader();
$config = $loader->load($credentials_file)->export()['acquia'];

// Get a CloudAPI client object.
$connector = new Connector($config);
$cloudapi = Client::factory($connector);

// Examples

// Get all applications.
// $applications = $cloudapi->applications();

// Get all environments of an application.
// $environments = $cloudapi->environments($project->organisation);

// Get one environment.
// $environment = $cloudapi->environment($project->environments->dev->uuid);
