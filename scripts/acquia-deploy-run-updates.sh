#!/usr/bin/env bash

# Run updates against environments that match the current branch.

# @todo this script needs a refresh per the other scripts in the directory.

if [ ! -f ~/.acquia/cloudapi.conf ]; then
  echo "Missing ~/.acquia/cloudapi.conf in this environment."
  exit 1
fi

# Defer to CI variables, if set.
if [ -n "${BUILDKITE_BRANCH+set}" ] ; then
  BRANCH=$BUILDKITE_BRANCH
else
  BRANCH=`git rev-parse --abbrev-ref HEAD`
fi

if [ -z "$BRANCH" ] ; then
  echo "Variable BRANCH not set. This script updates any Acquia environment that matches this branch."
  exit 1
fi

# See if any environment matches the current branch and update them.
for env in dev test ra; do

  # Examine the current branch on this Acquia environment.
  ENV_BRANCH=`./scripts/util-acquia-get-branch-sdk-v1.sh $env`

  # Run updates on Acquia if the Acquia branch matches the Buildkite branch.
  if [ "$ENV_BRANCH" = "$BRANCH" ] ; then
    DRUSH_ALIAS=`composer config extra.acquia.environments.$env.alias`
    drush "$DRUSH_ALIAS" cache-rebuild
    drush "$DRUSH_ALIAS" php-eval "\Drupal::cache('discovery')->invalidateAll();"
    drush "$DRUSH_ALIAS" cache-rebuild
    drush "$DRUSH_ALIAS" config-import --source=../config/default -y
    drush "$DRUSH_ALIAS" updatedb -y
    drush "$DRUSH_ALIAS" cache-rebuild
    drush "$DRUSH_ALIAS" search-api:reset-tracker acquia_search_index -y
    drush "$DRUSH_ALIAS" p:invalidate everything -y

  else
    echo "Skipping Acquia '$env', which is on '$ENV_BRANCH', and doesn't match '$BRANCH'"
  fi

done

