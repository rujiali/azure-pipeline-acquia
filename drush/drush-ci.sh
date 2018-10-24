#!/usr/bin/env bash

# Wrapper around drush to handle syncronous dealings with Acquia.

set -euo pipefail

pwd
cd ..

# Use only config from .drush directory.
DRUSH_CONFIG="--local --config=$DIR/drush/drushrc.php --include=$DIR/drush --alias-path=$DIR/drush"
DRUSH_COMMAND="vendor/bin/drush "$@" $DRUSH_CONFIG"

AC_COMMAND="$2"
if [[ $AC_COMMAND = "ac-code-deploy" ]] ||
   [[ $AC_COMMAND = "ac-code-path-deploy" ]] ||
   [[ $AC_COMMAND = "ac-database-add" ]] ||
   [[ $AC_COMMAND = "ac-database-copy" ]] ||
   [[ $AC_COMMAND = "ac-database-delete" ]] ||
   [[ $AC_COMMAND = "ac-database-instance-backup" ]] ||
   [[ $AC_COMMAND = "ac-database-instance-backup-delete" ]] ||
   [[ $AC_COMMAND = "ac-database-instance-backup-restore" ]] ||
   [[ $AC_COMMAND = "ac-domain-add" ]] ||
   [[ $AC_COMMAND = "ac-domain-delete" ]] ||
   [[ $AC_COMMAND = "ac-domain-move" ]] ||
   [[ $AC_COMMAND = "ac-domain-purge" ]] ||
   [[ $AC_COMMAND = "ac-environment-install" ]] ||
   [[ $AC_COMMAND = "ac-files-copy" ]]
then

  JSON="$($DRUSH_COMMAND --format=json)"
  TASK="$(echo $JSON | jq -crM '.id')"
  echo "Acquia Cloud task: $TASK"

  STATE="null"
  while [[ $STATE == "null" ]]
  do
    # Wait 3 seconds between cloud checks.
    sleep 3
    JSON="$(vendor/bin/drush $1 ac-task-info $TASK --format=json $DRUSH_CONFIG)"
    echo "$(echo $JSON | jq -crM '.description')..."
    STATE="$(echo $JSON | jq -crM '.completed')"
  done

  echo "$(echo $JSON | jq -crM '.description')... Done."

else

  # Fall back to standard drush.
  ($DRUSH_COMMAND)

fi
