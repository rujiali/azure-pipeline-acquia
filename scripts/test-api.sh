#!/usr/bin/env bash

# The purpose of this test is to install the site from scratch, and fail
# the build if the site doesn't appear to be working.
# We use `drush site-install` and Behat to achieve this.

# Warning, this script is run by Buildkite agent only. It would be good to
# make it possible to run in the VM for testing, in the same way that
# `scripts/deploy-acquia.sh` is run from both pipelines.yml and the `composer deploy`
# custom script (composer.json).

set -euo pipefail

if [ -n "${BUILDKITE_BUILD_CHECKOUT_PATH+x}" ] ; then
  SOURCE=$BUILDKITE_BUILD_CHECKOUT_PATH
  cp "$SOURCE"/docroot/sites/default/settings/example.settings.buildkite.php "$SOURCE"/docroot/sites/default/settings/settings.local.php
  TEST_DOMAIN="http://ci.worksafe.vm"
else
  SOURCE=`pwd`
  TEST_DOMAIN="http://worksafe.vm"
fi

# Connect to the buildkite database and set useful config.

cd docroot

# Sync database from test.
echo "Installing site from test data."
drush sql-drop -y

../vendor/bin/drush --alias-path=../drush --local @test sql-dump > /tmp/test.sql
../vendor/bin/drush sql-cli < /tmp/test.sql
../vendor/bin/drush --local --uri=default cache-rebuild -y
echo "Importing latest config and running updates."
../vendor/bin/drush --local --uri=default updatedb -y
../vendor/bin/drush --local --uri=default config-import --source=../config/default -y
../vendor/bin/drush --local --uri=default cache-rebuild -y

cd $SOURCE

echo "Running API tests against $TEST_DOMAIN."
CI_TEST_DOMAIN=$TEST_DOMAIN ./vendor/bin/phpunit ./tests/api

exit
