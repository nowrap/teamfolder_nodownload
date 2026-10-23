<?php

namespace OCA\TeamfolderNoDownload;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\BeforeDirectFileDownloadEvent;
use OCP\Files\Events\BeforeZipCreatedEvent;
use OCP\Files\NotFoundException;

/** @template-implements IEventListener<Event> */
class DownloadListener implements IEventListener {
	private const MESSAGE = 'Download is disabled for this team folder';

	public function __construct(
		private Rules $rules,
	) {
	}

	public function handle(Event $event): void {
		try {
			if ($event instanceof BeforeZipCreatedEvent && $this->zipBlocked($event)) {
				// error message wins even if a later listener sets successful again
				$event->setErrorMessage(self::MESSAGE);
				$event->setSuccessful(false);
			} elseif ($event instanceof BeforeDirectFileDownloadEvent
				&& ($node = $this->rules->userFolder()?->get($event->getPath())) !== null
				&& $this->rules->blockedNode($node)) {
				$event->setErrorMessage(self::MESSAGE);
				$event->setSuccessful(false);
			}
		} catch (NotFoundException) {
			// nothing to protect
		}
	}

	private function zipBlocked(BeforeZipCreatedEvent $event): bool {
		$folder = $event->getFolder();
		if ($folder === null) {
			return false;
		}
		$nodes = $event->getFiles() === [] ? [$folder] : array_map(fn ($name) => $folder->get($name), $event->getFiles());
		foreach ($nodes as $node) {
			if ($this->rules->blockedNode($node, true)) {
				return true;
			}
		}
		return false;
	}
}
