<?php

namespace OCA\TeamfolderNoDownload;

use OCA\GroupFolders\Folder\FolderManager;
use OCP\Constants;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Mount\IMountManager;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\Node;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUserSession;
use OCP\Server;

class Rules {
	public const APP = 'teamfolder_nodownload';

	/** @var array<int, bool> */
	private array $cache = [];
	/** @var array<int, list<string>>|null */
	private ?array $folderGroups = null;
	/** @var array<int, list<string>>|null */
	private ?array $rules = null;

	public function __construct(
		private IAppConfig $config,
		private IUserSession $session,
		private IGroupManager $groupManager,
		private IMountManager $mountManager,
		private IRootFolder $rootFolder,
	) {
	}

	/** @return array<int, list<string>> folder id => group ids without download */
	public function get(): array {
		return $this->rules ??= $this->config->getValueArray(self::APP, 'blocked');
	}

	public function set(array $blocked): void {
		$this->config->setValueArray(self::APP, 'blocked', $blocked);
		$this->rules = $blocked;
		$this->cache = [];
	}

	/** @return array<int, list<string>> folder id => group ids with read access to the folder (circles left out) */
	public function folderGroups(): array {
		if ($this->folderGroups === null) {
			$this->folderGroups = [];
			// groupfolders may be disabled; never break DAV because of that
			if (class_exists(FolderManager::class)) {
				foreach (Server::get(FolderManager::class)->getAllFolders() as $id => $folder) {
					// a mapping without read permission grants no access, so it must not lift a block
					$groups = array_filter($folder->groups, fn ($g) => ($g['type'] ?? 'group') === 'group'
						&& (($g['permissions'] ?? 0) & Constants::PERMISSION_READ));
					$this->folderGroups[$id] = array_map('strval', array_keys($groups));
				}
			}
		}
		return $this->folderGroups;
	}

	public function blockedForCurrentUser(int $folderId): bool {
		if (empty($this->get()[$folderId])) {
			return false; // no rule: skip loading all team folders
		}
		if (!isset($this->cache[$folderId])) {
			$user = $this->session->getUser();
			$this->cache[$folderId] = $user !== null && self::blocks(
				$this->groupManager->getUserGroupIds($user),
				$this->folderGroups()[$folderId] ?? [],
				$this->get()[$folderId],
			);
		}
		return $this->cache[$folderId];
	}

	public function blockedMount(?IMountPoint $mount): bool {
		return $mount !== null
			&& $mount->getMountType() === 'group'
			&& method_exists($mount, 'getFolderId')
			&& $this->blockedForCurrentUser($mount->getFolderId());
	}

	/** $nested: also look at team folders mounted below a folder (zip, copy of a parent) */
	public function blockedNode(Node $node, bool $nested = false): bool {
		if ($this->get() === []) {
			return false;
		}
		if ($this->blockedMount($node->getMountPoint())) {
			return true;
		}
		if ($nested && $node instanceof Folder) {
			foreach ($this->mountManager->findIn($node->getPath()) as $mount) {
				if ($this->blockedMount($mount)) {
					return true;
				}
			}
		}
		return false;
	}

	public function userFolder(): ?Folder {
		$user = $this->session->getUser();
		return $user === null ? null : $this->rootFolder->getUserFolder($user->getUID());
	}

	/** Blocked only if every group that gives the user access is blocked (one allowed group wins). */
	public static function blocks(array $userGroups, array $folderGroups, array $blocked): bool {
		$via = array_intersect($userGroups, $folderGroups);
		return $via !== [] && array_diff($via, $blocked) === [];
	}
}
