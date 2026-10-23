<?php

namespace OCA\TeamfolderNoDownload\AppInfo;

use OCA\TeamfolderNoDownload\DownloadListener;
use OCA\TeamfolderNoDownload\OnlyOfficeMiddleware;
use OCA\TeamfolderNoDownload\Rules;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\Events\BeforeDirectFileDownloadEvent;
use OCP\Files\Events\BeforeZipCreatedEvent;

class Application extends App implements IBootstrap {
	public function __construct(array $urlParams = []) {
		parent::__construct(Rules::APP, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(BeforeZipCreatedEvent::class, DownloadListener::class);
		$context->registerEventListener(BeforeDirectFileDownloadEvent::class, DownloadListener::class);
		$context->registerMiddleware(OnlyOfficeMiddleware::class, true);
	}

	public function boot(IBootContext $context): void {
	}
}
