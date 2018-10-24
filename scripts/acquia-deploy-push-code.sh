#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

#/
#/ Creates an artefact and pushes the code to Acquia.
#/
#/ This script assumes you've set up a public key in Acquia and cloudapi.conf
#/ file for the user running the script. For CI purposes the required files are
#/ provisioned.
#/

# @see https://dev.to/thiht/shell-scripts-matter.
usage() { grep '^#/' "$0" | cut -c4- ; exit 0 ; }
expr "$*" : ".*--help" > /dev/null && usage
readonly LOG_FILE="/tmp/$(basename "$0").log"
info()    { echo "[INFO]  $*" | tee -a "$LOG_FILE" >&2 ; }
error()   { echo "[FATAL] $*" | tee -a "$LOG_FILE" >&2 ; exit 1 ; }

if [ ! -f ~/.acquia/cloudapi.conf ]; then
  fatal "Missing ~/.acquia/cloudapi.conf in this environment."
fi

#branch=`git rev-parse --abbrev-ref HEAD`
branch=develop
source=`pwd`

user=`whoami`
acquia_remote=`composer config extra.acquia.git-remote`
build_path=/tmp/worksafe-deploy
commit=`git log -n 1  --pretty=format:"%h"`

info "User: $user"
info "Remote: $acquia_remote"
info "Branch: $branch at $commit"
info "Source directory: $source"
info "Build directory: $build_path"

if [ ! -d "$build_path/.git" ]; then
  git clone $acquia_remote $build_path
fi

info "Preparing Acquia repo on branch $branch"
cd "$build_path"
git remote set-url origin "$acquia_remote"
git reset --hard
git clean -fd
git fetch
# Need to switch to a temporary branch and handle when Acquia branches diverge, which they can do a lot.
unique=`date +%s`
git fetch
git checkout -b "$unique"
git branch -D "$branch" || true
git checkout -b "$branch" "origin/$branch"
git pull

info "Copying files from Github repo to Acquia repo"
rsync -rl --stats --delete --exclude ".git/"  --exclude ".vault_pass" --exclude "acquia-repo-only" --exclude "latest-sanitised-db.sql" --exclude "acquiacli.yml" --exclude "docroot/sites/default/files/" "$source"/ "$build_path"/
cp "$source"/.gitignore.artefact "$build_path"/.gitignore
find ./* -name '.git' -type d -prune -exec rm -rf "{}" \;
git config --local core.eol lf
git config --local core.autocrlf input
git config --local core.safecrlf false

info "Committing files to Acquia repo"
echo "$commit at "`date +%Y%m%d_%H%M` > "$build_path/docroot/BUILD"
git add .
git commit -m "Deploy '$branch' branch at $commit"

info "Checking all changes are committed before pushing"
git status
git diff-index --quiet HEAD --

git push origin $branch

if [ "release" = "$branch" ]; then
  tag=release_$(date +%Y%m%d_%H%M)
  info "Pushing a tag against the release branch: $tag"
  git tag $tag
  git push --tags
fi
