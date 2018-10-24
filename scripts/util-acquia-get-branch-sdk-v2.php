<?php

/**
 * @file
 * Get the current branch of an environment.
 */

// Bootstraps the typhonius/acquia-php-sdk-v2 library.
include './scripts/util-acquia-sdk-bootstrap.php';

/** @var \AcquiaCloudApi\CloudApi\Client $cloudapi */
/** @var \stdClass $project */

// Simple argument processing to find the requested Acquia environment.
if (!in_array($argv[1], ['dev', 'test', 'prod', 'ra'])) {
  print "Expecting 'dev', 'test' or 'prod' as first argument.";
  exit(1);
}
else {
  $acquia_env = $argv[1];
}

$environment = $cloudapi->environment($project->environments->{$acquia_env}->uuid);

print trim($environment->vcs->path);
exit();
