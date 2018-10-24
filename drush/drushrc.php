<?php

$options['uri'] = 'http://worksafe.vm';
$options['alias-path'] = [__DIR__];

// This doesn't work for site aliases.
// @see https://github.com/drush-ops/drush-launcher/issues/56
// @see ./scripts/patches/drupal-local-only.patch
$options['local'] = TRUE;

//$options['ac-config'] = ~/cloudapi.conf
//