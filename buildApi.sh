#!/bin/bash
git checkout main
git pull origin main
echo "Syncing API"
rsync -avr --delete-after ../../api/ src/api/ --exclude 'api'
echo "Commiting and Pushing"
cd spa
git add --all
git commit -m "SPA Build  on `date +'%Y-%m-%d %H:%M:%S'`";
git push origin main
