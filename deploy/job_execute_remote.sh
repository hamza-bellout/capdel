#!/bin/bash

# cd-repository is used only as a backup plan
# proper packages should be deployed on AWS in job_prepare.sh step

set -e
if [ "$ENV" = 'PROD' ];
then
    echo "$ENV"

    #PROD uses different template that's why command format is different than on DEV
    cp web/sites/default/settings.php.prod web/sites/default/settings.php
    cp web/.htaccess.prod web/.htaccess
    cp web/robots.txt.prod web/robots.txt
    zip -r capdel_PROD_BACKUP.zip . -x /HTML/\*

elif [ "$ENV" = 'STAGING' ];
then
    echo "$ENV"

    #BETA uses different template that's why command format is different than on DEV
    cp web/sites/default/settings.php.beta.new web/sites/default/settings.php
    zip -r capdel_BETA_BACKUP.zip . -x /HTML/\*

elif [ "$ENV" = 'DEV' ];
then
  echo "$ENV"

  ./vendor/drush/drush/drush cr;
fi
