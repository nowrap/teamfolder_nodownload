<?php

namespace OCA\TeamfolderNoDownload\Settings;

use OCA\TeamfolderNoDownload\Rules;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class Section implements IIconSection {
	public function __construct(
		private IURLGenerator $url,
		private IL10N $l,
	) {
	}

	public function getID(): string {
		return Rules::APP;
	}

	public function getName(): string {
		return $this->l->t('Team folders: download');
	}

	public function getPriority(): int {
		// directly above "Team folders" (90): other sections share 90 (Logging, System) and same priority keeps load order
		return 89;
	}

	public function getIcon(): string {
		return $this->url->imagePath('core', 'actions/download.svg');
	}
}
