<?php

namespace OCA\TeamfolderNoDownload\Dav;

use OCA\DAV\Connector\Sabre\Node;
use OCA\Files_Trashbin\Sabre\ITrash;
use OCA\Files_Versions\Sabre\VersionFile;
use OCA\GroupFolders\Trash\GroupTrashItem;
use OCA\TeamfolderNoDownload\Rules;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\INode;
use Sabre\DAV\PropFind;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\HTTP\RequestInterface;

class NoDownloadPlugin extends ServerPlugin {
	private Server $server;

	public function __construct(
		private Rules $rules,
	) {
	}

	public function initialize(Server $server): void {
		$this->server = $server;
		// before core FilesPlugin (100), so its hide-download handler does not overwrite ours
		$server->on('propFind', [$this, 'propFind'], 90);
		// app plugins are added during beforeMethod:*, so beforeMethod:GET is already over; core ViewOnlyPlugin uses method:* too
		$server->on('method:GET', [$this, 'beforeGet'], 80);
		$server->on('method:COPY', [$this, 'beforeCopyMove'], 80);
		$server->on('method:MOVE', [$this, 'beforeCopyMove'], 80);
	}

	public function propFind(PropFind $propFind, INode $node): void {
		if ($node instanceof Node && $this->rules->blockedNode($node->getNode())) {
			// files list and viewer hide download/sync actions on this flag
			$propFind->set('{http://nextcloud.org/ns}hide-download', 'true');
		}
	}

	public function beforeGet(RequestInterface $request): void {
		$node = $this->nodeFor($request->getPath());
		if ($node === null || !$this->blocked($node)) {
			return;
		}
		// Viewer/previews in the web UI load from the page itself (img/fetch, Sec-Fetch-Site: same-origin) and must keep working.
		// Sync, mobile and WebDAV clients send no Sec-Fetch headers; opening the file URL directly is a navigation (document).
		// (Authorization is not reliable here: some setups do not pass it to PHP.)
		// ponytail: headers can be faked by a determined user; zip of a parent folder is caught by DownloadListener
		if ($request->getHeader('Sec-Fetch-Site') !== 'same-origin' || $request->getHeader('Sec-Fetch-Dest') === 'document') {
			throw new Forbidden('Download is disabled for this team folder');
		}
	}

	/** Copy/move out of a blocked team folder would give a downloadable copy; inside the same folder is fine. */
	public function beforeCopyMove(RequestInterface $request): void {
		$node = $this->nodeFor($request->getPath());
		// only files/ nodes: trash and version restores are MOVEs too and must keep working
		if (!$node instanceof Node || !$this->rules->blockedNode($node->getNode(), true)) {
			return;
		}
		$parentPath = dirname($this->server->getCopyAndMoveInfo($request)['destination']);
		$parent = $this->nodeFor($parentPath === '.' ? '' : $parentPath);
		if ($parent instanceof Node
			&& $parent->getNode()->getStorage()->getId() === $node->getNode()->getStorage()->getId()) {
			return;
		}
		throw new Forbidden('Copying or moving out of this team folder is disabled');
	}

	private function nodeFor(string $path): ?INode {
		try {
			return $this->server->tree->getNodeForPath($path);
		} catch (NotFound) {
			return null;
		}
	}

	private function blocked(INode $node): bool {
		if ($node instanceof Node) {
			return $this->rules->blockedNode($node->getNode());
		}
		// older versions of a file (versions sidebar)
		if ($node instanceof VersionFile) {
			$source = $node->getVersion()->getSourceFile();
			$mine = $this->rules->userFolder()?->getFirstNodeById($source->getId());
			return $mine !== null && $this->rules->blockedNode($mine);
		}
		// deleted files of a team folder (delete, download from trash, restore)
		if ($node instanceof ITrash && ($item = $node->getFileInfo()) instanceof GroupTrashItem) {
			return $this->rules->blockedForCurrentUser($item->folder->id);
		}
		return false;
	}
}
