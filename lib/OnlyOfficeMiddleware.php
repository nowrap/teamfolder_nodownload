<?php

namespace OCA\TeamfolderNoDownload;

use OCA\Onlyoffice\AppConfig as OnlyOfficeConfig;
use OCA\Onlyoffice\Controller\EditorApiController;
use OCA\Onlyoffice\Controller\EditorController;
use OCA\Onlyoffice\Vendor\Firebase\JWT\JWT;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use OCP\IRequest;
use OCP\Server;

/**
 * ONLYOFFICE serves files through its document server, not WebDAV, and decides on download/print/copy
 * only for shares. Registered globally, so it sees the ONLYOFFICE controllers (no-op when that app is missing).
 * ponytail: tied to ONLYOFFICE internals (controller names, JWT signing), check on ONLYOFFICE updates
 */
class OnlyOfficeMiddleware extends Middleware {
	public function __construct(
		private Rules $rules,
		private IRequest $request,
	) {
	}

	public function beforeController(Controller $controller, string $methodName): void {
		// "download as" in the editor hands out the file via Nextcloud
		if ($controller instanceof EditorController && $methodName === 'download' && $this->blockedFile()) {
			throw new DownloadBlockedException();
		}
	}

	public function afterException(Controller $controller, string $methodName, \Exception $exception): Response {
		if ($exception instanceof DownloadBlockedException) {
			return new JSONResponse(['message' => 'Download is disabled for this team folder'], Http::STATUS_FORBIDDEN);
		}
		throw $exception;
	}

	public function afterController(Controller $controller, string $methodName, Response $response): Response {
		if (!$controller instanceof EditorApiController || $methodName !== 'config'
			|| !$response instanceof JSONResponse || !$this->blockedFile()) {
			return $response;
		}
		$params = $response->getData();
		if (!isset($params['document'])) {
			return $response; // error response
		}
		foreach (['download', 'print', 'copy'] as $permission) {
			$params['document']['permissions'][$permission] = false;
		}
		// the document server trusts the signed token, so sign the changed config again
		if (isset($params['token'])) {
			unset($params['token']);
			$params['token'] = JWT::encode($params, Server::get(OnlyOfficeConfig::class)->getDocumentServerSecret(), 'HS256');
		}
		$response->setData($params);
		return $response;
	}

	private function blockedFile(): bool {
		$fileId = (int)$this->request->getParam('fileId');
		$node = $fileId > 0 ? $this->rules->userFolder()?->getFirstNodeById($fileId) : null;
		return $node !== null && $this->rules->blockedNode($node);
	}
}
