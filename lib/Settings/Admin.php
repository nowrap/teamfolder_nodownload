<?php

namespace OCA\TeamfolderNoDownload\Settings;

use OCA\GroupFolders\Folder\FolderManager;
use OCA\TeamfolderNoDownload\Rules;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;
use OCP\Server;
use OCP\Util;

class Admin implements ISettings {
	public function __construct(
		private Rules $rules,
	) {
	}

	public function getForm(): TemplateResponse {
		$names = [];
		if (class_exists(FolderManager::class)) {
			foreach (Server::get(FolderManager::class)->getAllFolders() as $id => $folder) {
				$names[$id] = $folder->mountPoint;
			}
		}
		asort($names);
		Util::addTranslations(Rules::APP);
		Util::addScript(Rules::APP, 'admin');
		Util::addStyle(Rules::APP, 'admin');
		return new TemplateResponse(Rules::APP, 'admin', [
			'names' => $names,
			'groups' => $this->rules->folderGroups(),
			'blocked' => $this->rules->get(),
		], '');
	}

	public function getSection(): string {
		// own page: no tab API on the team folders page, and below its table sits a full screen of empty space (min-height: 100vh)
		return Rules::APP;
	}

	public function getPriority(): int {
		return 50;
	}
}
