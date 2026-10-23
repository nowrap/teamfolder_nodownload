#!/usr/bin/env bash
# E2E check against a running Nextcloud with the app enabled.
# Setup (once): team folder $FOLDER containing $FILE, blocked user's group ticked "Download sperren",
# admin in a group of that folder without block. Passwords only via environment:
#   NC_URL=https://nextcloud.example NC_USER=tnd-test NC_PASS=… NC_ADMIN=admin NC_ADMIN_PASS=… tests/e2e.sh
# Leaves an extra version of $FILE and one trash entry behind.
set -u
: "${NC_URL:?}" "${NC_USER:?}" "${NC_PASS:?}" "${NC_ADMIN:?}" "${NC_ADMIN_PASS:?}"
FOLDER=${FOLDER:-Wissen}
FILE=${FILE:-test.txt}
U="$NC_USER:$NC_PASS"
A="$NC_ADMIN:$NC_ADMIN_PASS"
D="$NC_URL/remote.php/dav/files/$NC_USER"
VIEWER=(-H 'Sec-Fetch-Site: same-origin' -H 'Sec-Fetch-Dest: empty')
LINK=(-H 'Sec-Fetch-Site: same-origin' -H 'Sec-Fetch-Dest: document')
fails=0

code() { curl -sk -o /dev/null -w '%{http_code}' "$@"; }
expect() { # expect <name> <wanted> <got>
	if [ "$3" = "$2" ]; then echo "ok   $1 ($3)"; else echo "FAIL $1: wanted $2, got $3"; fails=$((fails + 1)); fi
}
fileid() {
	curl -sk -u "$1" -X PROPFIND -H 'Depth: 0' \
		-d '<d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns"><d:prop><oc:fileid/></d:prop></d:propfind>' "$2" |
		grep -o '<oc:fileid>[0-9]*' | grep -o '[0-9]*$'
}
direct() { code -u "$1" -H 'OCS-APIRequest: true' -X POST -d "fileId=$2" "$NC_URL/ocs/v2.php/apps/dav/api/v1/direct"; }

hide=$(curl -sk -u "$U" -X PROPFIND -H 'Depth: 0' \
	-d '<d:propfind xmlns:d="DAV:" xmlns:nc="http://nextcloud.org/ns"><d:prop><nc:hide-download/></d:prop></d:propfind>' \
	"$D/$FOLDER/$FILE" | grep -o '<nc:hide-download>[^<]*' | sed 's/.*>//')
expect "PROPFIND hide-download" true "$hide"

expect "GET like sync client" 403 "$(code -u "$U" "$D/$FOLDER/$FILE")"
expect "GET opened as link" 403 "$(code -u "$U" "${LINK[@]}" "$D/$FOLDER/$FILE")"
expect "GET by viewer" 200 "$(code -u "$U" "${VIEWER[@]}" "$D/$FOLDER/$FILE")"
expect "GET by admin" 200 "$(code -u "$A" "$NC_URL/remote.php/dav/files/$NC_ADMIN/$FOLDER/$FILE")"

expect "COPY out of folder" 403 "$(code -u "$U" -X COPY -H "Destination: $D/e2e-copy.txt" "$D/$FOLDER/$FILE")"
expect "COPY folder out" 403 "$(code -u "$U" -X COPY -H "Destination: $D/e2e-folder" "$D/$FOLDER")"
expect "COPY inside folder" 201 "$(code -u "$U" -X COPY -H "Destination: $D/$FOLDER/e2e-inner.txt" "$D/$FOLDER/$FILE")"
expect "MOVE out of folder" 403 "$(code -u "$U" -X MOVE -H "Destination: $D/e2e-moved.txt" "$D/$FOLDER/e2e-inner.txt")"

expect "ZIP of home with folder" 403 "$(code -u "$U" -G --data-urlencode accept=zip --data-urlencode "files=[\"$FOLDER\"]" "$D/")"
expect "ZIP of whole home" 403 "$(code -u "$U" "${VIEWER[@]}" "$D/?accept=zip")"

expect "direct link" 403 "$(direct "$U" "$(fileid "$U" "$D/$FOLDER/$FILE")")"
printf 'e2e\n' | curl -sk -u "$U" -T - -o /dev/null "$D/e2e-own.txt"
expect "direct link own file" 200 "$(direct "$U" "$(fileid "$U" "$D/e2e-own.txt")")"
expect "ZIP of own file only" 200 "$(code -u "$U" "${VIEWER[@]}" -G --data-urlencode accept=zip --data-urlencode 'files=["e2e-own.txt"]' "$D/")"
code -u "$U" -X DELETE "$D/e2e-own.txt" >/dev/null

# a version appears once the admin overwrites the file
printf 'e2e %s\n' "$(date +%s)" | curl -sk -u "$A" -T - -o /dev/null "$NC_URL/remote.php/dav/files/$NC_ADMIN/$FOLDER/$FILE"
fid=$(fileid "$U" "$D/$FOLDER/$FILE")
version=$(curl -sk -u "$U" -X PROPFIND -H 'Depth: 1' "$NC_URL/remote.php/dav/versions/$NC_USER/versions/$fid" |
	grep -o '<d:href>[^<]*</d:href>' | sed 's#</*d:href>##g' | grep -v "/$fid/\$" | head -1)
expect "version GET like sync client" 403 "$(code -u "$U" "$NC_URL$version")"
expect "version GET by viewer" 200 "$(code -u "$U" "${VIEWER[@]}" "$NC_URL$version")"

code -u "$U" -X DELETE "$D/$FOLDER/e2e-inner.txt" >/dev/null
trash=$(curl -sk -u "$U" -X PROPFIND -H 'Depth: 1' "$NC_URL/remote.php/dav/trashbin/$NC_USER/trash/" |
	grep -o '<d:href>[^<]*e2e-inner[^<]*</d:href>' | sed 's#</*d:href>##g' | head -1)
expect "trash GET like sync client" 403 "$(code -u "$U" "$NC_URL$trash")"
expect "trash restore" 201 "$(code -u "$U" -X MOVE -H "Destination: $NC_URL/remote.php/dav/trashbin/$NC_USER/restore/$(basename "$trash")" "$NC_URL$trash")"
code -u "$U" -X DELETE "$D/$FOLDER/e2e-inner.txt" >/dev/null

# optional: ONLYOFFICE, OO_FILEID = file id of a document in the blocked folder
if [ -n "${OO_FILEID:-}" ]; then
	expect "ONLYOFFICE download as" 403 "$(code -u "$U" "$NC_URL/apps/onlyoffice/downloadas?fileId=$OO_FILEID")"
	perms=$(curl -sk -u "$U" -H 'OCS-APIRequest: true' -H 'Accept: application/json' \
		"$NC_URL/ocs/v2.php/apps/onlyoffice/api/v1/config/$OO_FILEID" | grep -o '"permissions":{[^}]*}')
	expect "ONLYOFFICE config download/print/copy" 3 "$(echo "$perms" | grep -o '"\(download\|print\|copy\)":false' | wc -l | tr -d ' ')"
fi

echo "$fails failed"
[ "$fails" -eq 0 ]
