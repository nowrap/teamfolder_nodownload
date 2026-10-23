<?php

namespace OCA\TeamfolderNoDownload\Controller;

use OCA\TeamfolderNoDownload\Rules;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

// admin only and CSRF-checked by default (no NoAdminRequired/NoCSRFRequired)
class SettingsController extends Controller {
	public function __construct(
		IRequest $request,
		private Rules $rules,
	) {
		parent::__construct(Rules::APP, $request);
	}

	public function save(array $blocked = []): JSONResponse {
		$clean = [];
		foreach ($blocked as $folderId => $groups) {
			$groups = array_values(array_filter(array_map('strval', (array)$groups), 'strlen'));
			if ($groups !== []) {
				$clean[(int)$folderId] = $groups;
			}
		}
		$this->rules->set($clean);
		return new JSONResponse($clean);
	}
}
