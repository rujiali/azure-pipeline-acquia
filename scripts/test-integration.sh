#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

#/
#/ The purpose of this test is to install the site from the prod database
#/ and fail the build if the site doesn't appear to be working with the
#/ new code.
#/

# @see https://dev.to/thiht/shell-scripts-matter.
usage() { grep '^#/' "$0" | cut -c4- ; exit 0 ; }
expr "$*" : ".*--help" > /dev/null && usage
readonly LOG_FILE="/tmp/$(basename "$0").log"
info()    { echo "[INFO]  $*" | tee -a "$LOG_FILE" >&2 ; }
error()   { echo "[FATAL] $*" | tee -a "$LOG_FILE" >&2 ; exit 1 ; }

# Determine if we are inside or outside the VM, or a running buildkite job.
if [ "$(whoami)" = "buildkite" ] ; then
  info "Running in CI context (using buildkite database)"
  source=$BUILDKITE_BUILD_CHECKOUT_PATH
  cp "$source"/docroot/sites/default/settings/example.settings.buildkite.php "$source"/docroot/sites/default/settings/settings.local.php
  alias="@self"

  # Only sync database in CI context.
  ./scripts/drupal-sync.sh @prod "$alias"

elif [ "$(whoami)" = "vagrant" ]; then
  info "Running in test machine as vagrant using drupal database (no buildkite conflict)"
  source="/var/www/drupalvm"
  alias="@self"
else
  info "Running from host machine as vagrant using drupal database (no buildkite conflict)"
  source=`pwd`
  alias="@vm"
fi

info "Running behat tests."
if [ "$alias" = "@vm" ]; then
  vagrant ssh --command "cd /var/www/drupalvm && ./vendor/bin/behat"
else
  ./vendor/bin/behat
fi
