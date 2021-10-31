#!/bin/bash

composer install

set -e

# allow normal environment sync for DEV environment
if [ "$ENV" = 'DEV' ];
then
  echo "$ENV"
  exit;
fi

# set deploy date and get git tag, if any
DEPLOY_DATE=`date +"%Y-%m-%d %T"`
GIT_TAG=$(git tag -l --contains HEAD | xargs)

if [ "$ENV" = 'PROD' ];
then
    echo "$ENV"

    #PROD uses different template that's why command format is different than on DEV
    cp web/sites/default/settings.php.prod web/sites/default/settings.php
    cp web/.htaccess.prod web/.htaccess
    cp web/robots.txt.prod web/robots.txt
    zip -r capdel_PROD.zip . -x /HTML/\*

elif [ "$ENV" = 'STAGING' ];
then
    echo "$ENV"
    #BETA uses different template that's why command format is different than on DEV
    cp web/sites/default/settings.php.beta.new web/sites/default/settings.php
fi

if [ -z "$GIT_TAG" ]
  then
    ZIPNAME="${GO_PIPELINE_COUNTER}_${GO_STAGE_COUNTER}_${GO_REVISION}_${ENV}.zip"

  else
    ZIPNAME="${GO_PIPELINE_COUNTER}_${GO_STAGE_COUNTER}_${GIT_TAG}_${GO_REVISION}_${ENV}.zip"
fi

#prepare zip with proper naming
zip -r "${ZIPNAME}" . -x /HTML/\* /web/sites/default/files/\* /.git/\* /web/themes/capdel/node_modules/\*

#upload to Capdels AWS S3
AWS_DEFAULT_REGION=eu-west-3 AWS_ACCESS_KEY_ID=${AWS_KEY} AWS_SECRET_ACCESS_KEY=${AWS_SECRET} aws s3 cp "${ZIPNAME}"  s3://capdel-web-artifacts/

#create version for elasticbeanstalk, DEV can deploy specific version from the console dash
AWS_DEFAULT_REGION=eu-west-3 AWS_ACCESS_KEY_ID=${AWS_KEY} AWS_SECRET_ACCESS_KEY=${AWS_SECRET} aws elasticbeanstalk create-application-version --application-name Capdel --version-label "${ZIPNAME}" --description "${DEPLOY_DATE} goCD auto upload" --source-bundle S3Bucket="capdel-web-artifacts",S3Key="${ZIPNAME}"
