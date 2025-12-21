#!/bin/bash
echo "Syncing"
rsync -av --delete-after /home/master/applications/stage_learnflow/public_html/ /home/master/applications/learnflow/public_html/ --exclude 'portal' --exclude 'igc' --exclude '.git' --exclude '.htaccess' --exclude '.env' --exclude 'api/v1/public/filestore' --exclude 'api/v1/logs' --exclude 'mobile'
chmod -R 777 /home/master/applications/learnflow/public_html/api/v1/public/filestore
chmod -R 777 /home/master/applications/learnflow/public_html/api/v1/logs
