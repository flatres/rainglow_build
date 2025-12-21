#!/bin/bash
git stash
git pull origin master
echo "Updating Composer"
cd src/api
composer update
echo "Syncing"
rsync -av --omit-dir-times --delete-after /home/master/build/src/ /home/master/applications/stage_learnflow/public_html/ --exclude 'portal' --exclude 'igc' --exclude '.git' --exclude '.htaccess' --exclude '.env' --exclude 'api/v1/public/filestore' --exclude 'api/v1/logs' --exclude 'mobile'
chmod -R 777 /home/master/applications/stage_learnflow/public_html/api/v1/public/filestore
chmod -R 777 /home/master/applications/stage_learnflow/public_html/api/v1/logs
