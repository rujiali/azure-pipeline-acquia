#!/usr/bin/php
<?php

/**
 * @file
 * Trigger a database backup.
 */

/** @var \AcquiaCloudApi\CloudApi\Client $cloudapi */
/** @var \stdClass $project */
include './scripts/util-acquia-sdk-bootstrap.php';

if (!in_array($argv[1], ['dev', 'test', 'prod'])) {
  print "Expecting 'dev', 'test' or 'prod' as first argument.";
  exit(1);
}
else {
  $acquia_env = $argv[1];
}
$branch_tag = $argv[2];
$database = 'worksafe';

$uuid = $project->environments->{$acquia_env}->uuid;

print "Creating a backup\n";
$cloudapi->createDatabaseBackup($uuid, $database);

print "Switching branch to $branch_tag\n";
$cloudapi->switchCode($uuid, $branch_tag);
