#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

#/
#/ Check out a composer package as source, so that you an make
#/ changes and save the subsequent patch. The composer.json is
#/ never altered.
#/
#/ Usage:
#/    Pass a composer package name. See the output of the script
#/    for tips, and how to restore the state.
#/
#/ Examples:
#/    ./scripts/util-install-from-source.sh drupal/search_api_solr
#/

# @see https://dev.to/thiht/shell-scripts-matter.
usage() { grep '^#/' "$0" | cut -c4- ; exit 0 ; }
expr "$*" : ".*--help" > /dev/null && usage
readonly LOG_FILE="/tmp/$(basename "$0").log"
info()    { echo "[INFO]    $*" | tee -a "$LOG_FILE" >&2 ; }
warning() { echo "[WARNING] $*" | tee -a "$LOG_FILE" >&2 ; }
error()   { echo "[ERROR]   $*" | tee -a "$LOG_FILE" >&2 ; }
fatal()   { echo "[FATAL]   $*" | tee -a "$LOG_FILE" >&2 ; exit 1 ; }

# Argument 1, source alias.
package=${1:-"missing"}
if [ "$package" = "missing" ] ; then
  fatal "Please pass a composer package name."
fi

package_path=`./vendor/bin/locate-package "$package"`

info "Removing package $package_path and installing from source."
if [ -d "$package_path" ]; then rm -Rf "$package_path"; fi
composer install --prefer-source

info "The directory..."
info "  $package_path"
info "is now a git clone of $package at the correct branch/tag/commit."
info "Now you can do something like... "
info "  cd $package_path"
info "  # change some files."
info "  git diff > ../../../useful-change.patch"
info "  # Optionally at the patch to a public issue queue"
info "  # and refer to it in the composer.json patches section"
info "  # then to return to normal..."
info "  rm -Rf $package_path"
info "  composer install"
