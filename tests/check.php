<?php
// php tests/check.php — checks the rule logic without Nextcloud
require __DIR__ . '/../lib/Rules.php';

use OCA\TeamfolderNoDownload\Rules;

$check = function (bool $ok, string $what): void {
	if (!$ok) {
		throw new RuntimeException("failed: $what");
	}
};
$check(Rules::blocks(['Externe_Wiss'], ['admin', 'Externe_Wiss'], ['Externe_Wiss']), 'blocked group blocks');
$check(!Rules::blocks(['Externe_Wiss', 'hkvz-admin'], ['hkvz-admin', 'Externe_Wiss'], ['Externe_Wiss']), 'allowed group wins');
$check(!Rules::blocks(['other'], ['Externe_Wiss'], ['Externe_Wiss']), 'no access via groups');
$check(!Rules::blocks(['Externe_Wiss'], ['Externe_Wiss'], []), 'no rule');
echo "ok\n";
