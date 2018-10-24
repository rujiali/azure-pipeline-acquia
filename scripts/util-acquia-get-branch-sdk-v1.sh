#!/usr/bin/env bash

# Uses Acquia Cloud API V1 to get the current branch of an environment.
# @see acquia-get-branch.php for API V2 example.

if [ ! -f ~/.acquia/cloudapi.conf ]; then
  echo "Missing ~/.acquia/cloudapi.conf in this environment."
  exit 1
fi

if [ -z "$1" ] ; then
  echo "Pass an argument 'dev', 'test', 'ra' or 'prod', eg ./scripts/acquia-get-branch.sh dev"
  exit 1
fi

EMAIL=`cat ~/.acquia/cloudapi.conf | jq .email --raw-output`
KEY=`cat ~/.acquia/cloudapi.conf | jq .key --raw-output`
REALM=`composer config extra.acquia.realm`
SITE=`composer config extra.acquia.site`

# Ask for environment info https://cloudapi.acquia.com/#GET__sites__site_envs-instance_route
RESULT=`curl -s -u "${EMAIL}":"${KEY}"  https://cloudapi.acquia.com/v1/sites/${REALM}:${SITE}/envs.json`

# Print the current branch, outputs nothing if no match.
echo $RESULT | jq '.[] | select(.name=="'$1'") .vcs_path' --raw-output
