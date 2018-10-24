#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

#/
#/ Makes a backup of Acquia prod, and deploys Acquia prod
#/ to a valid tag that is pointing to the tip of the release
#/ branch.
#/

# @see https://dev.to/thiht/shell-scripts-matter.
usage() { grep '^#/' "$0" | cut -c4- ; exit 0 ; }
expr "$*" : ".*--help" > /dev/null && usage
readonly LOG_FILE="/tmp/$(basename "$0").log"
info()    { echo "[INFO]  $*" | tee -a "$LOG_FILE" >&2 ; }
fatal()   { echo "[FATAL] $*" | tee -a "$LOG_FILE" >&2 ; exit 1 ; }

if [ ! -f ~/.acquia/cloudapi.conf ]; then
  echo "Missing ~/.acquia/cloudapi.conf in this environment."
  exit 1
fi

fatal "This script hasn't been used for a while, please verify."

user=`whoami`
acquia_remote=`composer config extra.acquia.git-remote`
acquia_realm=`composer config extra.acquia.realm`
acquia_site=`composer config extra.acquia.site`
acquia_env="prod"
source_path=`pwd`
build_path=/tmp/buildkite-deploy
release_branch="release"

info "User: $user"
info "Acquia remote: $acquia_remote"
info "Acquia realm: $acquia_realm"
info "Acquia site: $acquia_site"
info "Build directory: $build_path"

if [ ! -d "$build_path/.git" ]; then
  git clone $acquia_remote $build_path
fi

info "Preparing Acquia repo at $build_path"
cd $build_path
git remote set-url origin $acquia_remote
git reset --hard
git clean -fd
git fetch
git checkout "$release_branch"
git pull

info "Looking for a valid commit tag on the release branch"
commit=`git log -n 1  --pretty=format:"%H"`
info "Commit found: $commit"
release_tag=`git tag --contains $commit | grep ^release_` || release_tag="notfound"
info "Release tag found: $release_tag"
if [ "$release_tag" = "notfound" ] || [ "$commit" != `git rev-list -n 1 "$release_tag"` ] ; then
  fatal "There is not a valid release_* tag at the tip of the $release_branch branch"
fi

read -p "!! Switch Acquia prod to $release_tag? " -n 1 -r
if [[ ! $REPLY =~ ^[Yy]$ ]] ; then
  echo
  info "Cancelled"
  exit
fi

echo
cd $source_path

info "Switching $acquia_env to $release_tag"
php ./scripts/util-acquia-switch-branch.php $acquia_env $release_tag

info "Running prod updates"
drush @prod cache-rebuild
drush -y @prod updatedb -y
