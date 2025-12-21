#!/bin/bash
git stash
git pull origin main
echo "Updating Composer"
cd src/api
composer update
echo "Syncing"
rsync -av --omit-dir-times --delete-after /home/master/rainglow/src/ /home/master/applications/rainglow/public_html/ --exclude 'portal' --exclude 'igc' --exclude '.git' --exclude '.htaccess' --exclude '.env' --exclude 'api/v1/public/filestore' --exclude 'api/v1/logs' --exclude 'mobile'
chmod -R 777 /home/master/applications/rainglow/public_html/api/v1/public/filestore
chmod -R 777 /home/master/applications/rainglow/public_html/api/v1/logs
