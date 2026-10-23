# Team folder: no download

Nextcloud app that restricts downloads in [team folders](https://github.com/nextcloud/groupfolders)
per folder and group. Members of a blocked group can still **view and edit** files, but cannot
download them. Workaround for [nextcloud/groupfolders#1586](https://github.com/nextcloud/groupfolders/issues/1586).

> A hurdle, not DRM: whoever can view a file can still take a screenshot or fake browser headers.

## What is blocked

For a user whose every group with access to the folder is blocked:

| Path | Result |
|---|---|
| Download button in the files list, multi-select, viewer, "open locally" | hidden (`nc:hide-download`) |
| Desktop/mobile sync clients, WebDAV clients, opening the file URL directly | `403` |
| Copy or move out of the team folder (inside the folder still works) | `403` |
| ZIP download of the folder or of a parent folder containing it | refused |
| Older versions, deleted files in the trash (restore still works) | `403` |
| Direct download links (`/ocs/v2.php/apps/dav/api/v1/direct`) | refused |
| ONLYOFFICE editor: download, print, copy | disabled; "download as" (`/apps/onlyoffice/downloadas`) `403` |
| Viewer, previews, upload, edit | **allowed** |

One group without a block wins: a user who is also in an allowed group (e.g. admins) may download.
Only groups with read permission count.

## Known limits

- **Shares:** a public link or an internal share bypasses the rule. Remove the *Share* permission from
  blocked groups.
- **Teams (circles)** mapped to a folder are not taken into account.
- The web UI is told apart from clients by the browser's `Sec-Fetch-*` headers, which can be faked.
- Very old browsers without `Sec-Fetch-*` headers get `403` in the viewer, too.

## Install

Requires Nextcloud 33 or 34 with the Team folders app.

```bash
cd /path/to/nextcloud/apps   # or your writable apps path (occ config:system:get apps_paths)
git clone https://github.com/nowrap/teamfolder_nodownload.git
sudo -u www-data php occ app:enable teamfolder_nodownload
```

Then *Administration settings → Team folders: download*, tick **Block download** for a folder and
group, and save.

When upgrading Nextcloud to a major version not listed in `appinfo/info.xml`, Nextcloud disables the
app – and with it the block – until `max-version` is raised.

## How it works

- `lib/Dav/NoDownloadPlugin.php` – Sabre plugin: sets `nc:hide-download` on PROPFIND, refuses GET
  without `Sec-Fetch-Site: same-origin` (or with `Sec-Fetch-Dest: document`), refuses COPY/MOVE to
  another storage. Covers files, versions and team folder trash.
- `lib/DownloadListener.php` – listens to `BeforeZipCreatedEvent` and `BeforeDirectFileDownloadEvent`.
- `lib/OnlyOfficeMiddleware.php` – global middleware for the ONLYOFFICE connector: refuses "download as"
  and turns off download/print/copy in the editor config, re-signing it with the ONLYOFFICE JWT secret.
  Depends on ONLYOFFICE internals – check after ONLYOFFICE updates (`OO_FILEID` in `tests/e2e.sh`).
- `lib/Rules.php` – rules in app config, "blocked only if every access group is blocked".

## Tests

```bash
php tests/check.php                     # rule logic, no Nextcloud needed

# against a running instance: team folder "Wissen" with "test.txt", NC_USER's group blocked,
# NC_ADMIN in an allowed group of that folder
NC_URL=https://cloud.example NC_USER=blocked-user NC_PASS=… NC_ADMIN=admin NC_ADMIN_PASS=… \
  FOLDER=Wissen FILE=test.txt tests/e2e.sh
# add OO_FILEID=<file id of a document in that folder> to check ONLYOFFICE as well
```

## License

AGPL-3.0-or-later
