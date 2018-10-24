#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

#/
#/ Sync state from Prod another instance.
#/
#/ Usage:
#/    Pass a destination alias. The destination cannot be @prod.
#/    If the destination is omitted it will default to the local @vm.
#/
#/    Syncing from @prod may use a cached version. Pass --latest
#/    to override this.
#/
#/ Examples:
#/    ./scripts/drupal-sync.sh
#/    ./scripts/drupal-sync.sh @dev --latest
#/

# @see https://dev.to/thiht/shell-scripts-matter.
usage() { grep '^#/' "$0" | cut -c4- ; exit 0 ; }
expr "$*" : ".*--help" > /dev/null && usage
readonly LOG_FILE="/tmp/$(basename "$0").log"
info()    { echo "[INFO]    $*" | tee -a "$LOG_FILE" >&2 ; }
warning() { echo "[WARNING] $*" | tee -a "$LOG_FILE" >&2 ; }
error()   { echo "[ERROR]   $*" | tee -a "$LOG_FILE" >&2 ; }
fatal()   { echo "[FATAL]   $*" | tee -a "$LOG_FILE" >&2 ; exit 1 ; }

dumpname="latest-sanitised-db.sql"

# Argument 2, destination alias.
target=${1:-"@vm"}
latest=0

# Determine if the database should be dumped.
if [ "$target" = "--latest" ] ; then
  latest=1
  target="@vm"
fi
expr "$*" : ".*--latest" > /dev/null && latest=1

if [ "$latest" = "0" ] ; then
  cachedsql=$(find "$dumpname" -ctime 0 -size +1k 2>/dev/null || true)

  if [ ${cachedsql:-"notfound"} = "notfound" ] ; then
    # No recent database dump was found.
    latest=1
  fi
fi

info "Syncing @prod to $target"

# Care factor.
if [ "$target" = "@prod" ] ; then
  fatal "Target cannot be prod."
  exit
fi

if [ "$latest" = "1" ] ; then
  info "Dumping prod."
  rm -f "$dumpname"
  drush @prod sql:dump > "$dumpname"
fi

drush -y "$target" sql:drop
cat "$dumpname" | drush "$target" sql:cli
