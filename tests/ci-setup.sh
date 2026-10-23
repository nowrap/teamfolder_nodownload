#!/usr/bin/env bash
# Starts a throwaway Nextcloud in Docker with this app and the fixture tests/e2e.sh expects,
# then writes the e2e environment to $GITHUB_ENV (or prints it).
#   tests/ci-setup.sh 33
set -euo pipefail
VERSION=${1:?Nextcloud major version}
APP_DIR=$(cd "$(dirname "$0")/.." && pwd)
ADMIN_PASS="Admin-$(head -c 12 /dev/urandom | od -An -tx1 | tr -d ' \n')"
USER_PASS="Blocked-$(head -c 12 /dev/urandom | od -An -tx1 | tr -d ' \n')"
URL=http://localhost:8080

docker run -d --name nc -p 8080:80 \
	-e SQLITE_DATABASE=nextcloud -e NEXTCLOUD_ADMIN_USER=admin -e NEXTCLOUD_ADMIN_PASSWORD="$ADMIN_PASS" \
	-e NEXTCLOUD_TRUSTED_DOMAINS=localhost \
	-v "$APP_DIR:/var/www/html/custom_apps/teamfolder_nodownload:ro" \
	"nextcloud:$VERSION-apache" >/dev/null

for _ in $(seq 120); do
	curl -fs "$URL/status.php" | grep -q '"installed":true' && break
	sleep 2
done
curl -fs "$URL/status.php"; echo

occ() { docker exec -u www-data nc php occ "$@"; }
occ app:install groupfolders || occ app:enable groupfolders
occ app:enable teamfolder_nodownload

occ group:add blocked
docker exec -u www-data -e OC_PASS="$USER_PASS" nc php occ user:add --password-from-env --group blocked blocked
folder=$(occ groupfolders:create Wissen | tail -n1 | tr -d '\r')
occ groupfolders:group "$folder" admin read write share delete
occ groupfolders:group "$folder" blocked read write delete
occ config:app:set teamfolder_nodownload blocked --type array --value "{\"$folder\":[\"blocked\"]}"
printf 'test\n' | curl -fs -u "admin:$ADMIN_PASS" -T - "$URL/remote.php/dav/files/admin/Wissen/test.txt"

env="NC_URL=$URL
NC_USER=blocked
NC_PASS=$USER_PASS
NC_ADMIN=admin
NC_ADMIN_PASS=$ADMIN_PASS"
if [ -n "${GITHUB_ENV:-}" ]; then
	echo "::add-mask::$ADMIN_PASS"
	echo "::add-mask::$USER_PASS"
	echo "$env" >>"$GITHUB_ENV"
else
	echo "$env"
fi
