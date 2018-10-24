#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

#/
#/ Run updates and config imports for a drush alias.
#/
#/ Usage:
#/    Pass a target drush alias. Defaults to @vm.
#/
#/ Examples:
#/    ./scripts/drupal-updates.sh
#/    ./scripts/drupal-updates.sh @prod
#/

# @see https://dev.to/thiht/shell-scripts-matter.
usage() { grep '^#/' "$0" | cut -c4- ; exit 0 ; }
expr "$*" : ".*--help" > /dev/null && usage
readonly LOG_FILE="/tmp/$(basename "$0").log"
info()    { echo "[INFO]    $*" | tee -a "$LOG_FILE" >&2 ; }
warning() { echo "[WARNING] $*" | tee -a "$LOG_FILE" >&2 ; }
error()   { echo "[ERROR]   $*" | tee -a "$LOG_FILE" >&2 ; }
fatal()   { echo "[FATAL]   $*" | tee -a "$LOG_FILE" >&2 ; exit 1 ; }

# Default to @vm if missing arg, and test the alias.
target=${1:-"@vm"}
drush site:alias "$target" >> /dev/null

drush "$target" cache-rebuild
drush "$target" updatedb -y
drush "$target" config-import -y
drush "$target" search-api:reset-tracker acquia_search_index -y
drush "$target" p:invalidate everything -y
