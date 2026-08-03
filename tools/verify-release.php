<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';

$root = dirname(__DIR__);
$errors = [];
$checks = 0;

$pass = static function (bool $condition, string $message) use (&$errors, &$checks): void {
    $checks++;

    if (! $condition) {
        $errors[] = $message;
    }
};

$read = static function (string $path) use ($root, $pass): string {
    $absolute = $root.'/'.$path;
    $pass(is_file($absolute), sprintf('Required file is missing: %s', $path));

    if (! is_file($absolute)) {
        return '';
    }

    $contents = file_get_contents($absolute);
    $pass($contents !== false, sprintf('Required file could not be read: %s', $path));

    return $contents === false ? '' : $contents;
};

$stateJson = $read('docs/project-state.json');
$state = json_decode($stateJson, true);
$pass(is_array($state), 'docs/project-state.json is not valid JSON.');
$state = is_array($state) ? $state : [];

$application = require $root.'/bootstrap/app.php';
$application->make(Kernel::class)->bootstrap();

$version = (string) ($state['version'] ?? '');
$releaseName = (string) ($state['releaseName'] ?? '');
$groupCount = (int) ($state['officialScreenshotGroups'] ?? 0);
$latestGroup = (int) ($state['latestScreenshotGroup'] ?? 0);

$pass($version === '3.8.0', 'Canonical version must be 3.8.0.');
$pass($releaseName === 'Migration Qualification', 'Canonical release name must be Migration Qualification.');
$pass(($state['status'] ?? null) === 'closed', 'Canonical release status must be closed.');
$pass($groupCount === 33 && $latestGroup === 33, 'Canonical screenshot-group state must close at SG33.');
$pass(($state['realFamilyMigrationCompleted'] ?? null) === false, 'The real family migration must remain explicitly incomplete.');
$pass(($state['publicEvidenceUsesSyntheticData'] ?? null) === true, 'Public evidence must remain explicitly synthetic.');
$pass(config('release.version') === $version, 'config/release.php version disagrees with project state.');
$pass(config('release.name') === $releaseName, 'config/release.php release name disagrees with project state.');
$pass(str_contains((string) config('release.status'), '33'), 'config/release.php does not identify SG33 as closed.');

$requiredDocuments = [
    'README.md',
    'CHANGELOG.md',
    'RIGHTS_AND_LICENSING.md',
    'SECURITY.md',
    'docs/MASTER.md',
    'docs/CURRENT_BUCKET.md',
    'docs/CAPABILITIES.md',
    'docs/RELEASE_HISTORY.md',
    'docs/THREAT_MODEL.md',
    'docs/release-notes/v3.8.0.md',
    'docs/screenshot-groups/README.md',
];

foreach ($requiredDocuments as $document) {
    $read($document);
}

$readme = $read('README.md');
$rights = $read('RIGHTS_AND_LICENSING.md');
$roadmap = $read('docs/ROADMAP.md');
$overview = $read('docs/architecture/SYSTEM_OVERVIEW.md');
$releaseNotes = $read('docs/release-notes/v3.8.0.md');
$composerJson = $read('composer.json');
$composer = json_decode($composerJson, true);
$pass(is_array($composer), 'composer.json is not valid JSON.');
$composer = is_array($composer) ? $composer : [];

$pass(($composer['license'] ?? null) === 'proprietary', 'Composer license must remain proprietary.');
$pass(($composer['homepage'] ?? null) === 'https://familyarchive.bayforgesystems.com', 'Composer homepage disagrees with the live product URL.');
$pass(($composer['authors'][0]['name'] ?? null) === 'Codie Shannon', 'Composer creator metadata is missing Codie Shannon.');
$pass(str_contains($readme, 'RIGHTS_AND_LICENSING.md'), 'README does not link to the repository rights document.');
$pass(str_contains($readme, 'Codie Shannon'), 'README creator attribution is missing Codie Shannon.');
$pass(str_contains($rights, 'All rights reserved'), 'Rights document does not reserve repository rights.');
$pass(str_contains($rights, 'publicly viewable'), 'Rights document does not state the portfolio-review boundary.');

foreach ([$readme, $roadmap, $overview, $releaseNotes] as $document) {
    $pass(str_contains($document, '3.8.0'), 'A primary release document does not identify v3.8.0.');
    $pass(str_contains($document, '33'), 'A primary release document does not identify SG33.');
}

$pass(! str_contains($roadmap, 'Screenshot Groups 01-19 are closed'), 'Roadmap contains the stale SG01-19 closure statement.');
$pass(! str_contains($overview, 'Groups 01-19 are closed'), 'System overview contains the stale SG01-19 closure statement.');

$pngSignatures = [];
$forbiddenPngChunks = ['tEXt', 'zTXt', 'iTXt', 'eXIf'];
$forbiddenEvidenceText = [
    '/'.'cod'.'ex/i',
    '/'.'chat'.'gpt/i',
    '/[A-Z]:\\\\Users\\\\/i',
    '/E:\\\\Transfer/i',
    '/familyarchive-prod-codie-nz/i',
];

for ($group = 1; $group <= $groupCount; $group++) {
    $name = sprintf('screenshot-group-%02d', $group);
    $relative = 'docs/screenshot-groups/'.$name;
    $directory = $root.'/'.$relative;
    $pass(is_dir($directory), sprintf('Official evidence directory is missing: %s', $relative));

    if (! is_dir($directory)) {
        continue;
    }

    $read($relative.'/README.md');
    $evidenceIndex = $read($relative.'/Evidence_Index.md');
    $pngs = glob($directory.'/*.png') ?: [];
    $pass($pngs !== [], sprintf('%s contains no PNG evidence.', $relative));

    foreach ($forbiddenEvidenceText as $pattern) {
        $pass(preg_match($pattern, $evidenceIndex) !== 1, sprintf('%s contains a forbidden private or tooling reference.', $relative.'/Evidence_Index.md'));
    }

    foreach ($pngs as $png) {
        $bytes = file_get_contents($png);
        $label = $relative.'/'.basename($png);
        $pass($bytes !== false && str_starts_with($bytes, "\x89PNG\r\n\x1a\n"), sprintf('%s is not an encoded PNG.', $label));

        if ($bytes === false || ! str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            continue;
        }

        $dimensions = getimagesize($png);
        $pass(is_array($dimensions) && ($dimensions[0] ?? 0) >= 320 && ($dimensions[1] ?? 0) >= 240, sprintf('%s is below the evidence dimension floor.', $label));

        $offset = 8;
        $chunks = [];
        $length = strlen($bytes);

        while ($offset + 12 <= $length) {
            $chunkLength = unpack('Nlength', substr($bytes, $offset, 4));
            $chunkLength = (int) ($chunkLength['length'] ?? -1);
            $type = substr($bytes, $offset + 4, 4);

            if ($chunkLength < 0 || $offset + 12 + $chunkLength > $length) {
                $errors[] = sprintf('%s contains an invalid PNG chunk boundary.', $label);
                break;
            }

            $chunks[] = $type;
            $offset += 12 + $chunkLength;

            if ($type === 'IEND') {
                break;
            }
        }

        $pass(in_array('IHDR', $chunks, true) && in_array('IEND', $chunks, true), sprintf('%s has an incomplete PNG structure.', $label));
        $pass(array_intersect($forbiddenPngChunks, $chunks) === [], sprintf('%s contains forbidden metadata chunks.', $label));
        $pngSignatures[] = hash('sha256', $bytes);
    }
}

$pass(count($pngSignatures) === count(array_unique($pngSignatures)), 'Official screenshot evidence contains byte-identical duplicate PNGs.');

$trackedOutput = [];
$gitExit = 0;
exec('git -C '.escapeshellarg($root).' ls-files', $trackedOutput, $gitExit);
$pass($gitExit === 0, 'Tracked-file inventory could not be read from Git.');

$forbiddenTrackedPaths = [
    '/(^|\/)\.env$/',
    '/^output\//',
    '/^database\/.*\.sqlite$/',
    '/^storage\/.*\.(log|key)$/',
    '/(^|\/)auth\.json$/',
];

foreach ($trackedOutput as $path) {
    foreach ($forbiddenTrackedPaths as $pattern) {
        $pass(preg_match($pattern, $path) !== 1, sprintf('Private or runtime path is tracked: %s', $path));
    }
}

$secretPatterns = [
    '/AIza[0-9A-Za-z_-]{30,}/',
    '/AKIA[0-9A-Z]{16}/',
    '/github_pat_[0-9A-Za-z_]{20,}/',
    '/ghp_[0-9A-Za-z]{30,}/',
    '/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/',
    '/[A-Z]:\\\\Users\\\\[^\\\\\s]+\\\\/i',
    '/E:\\\\Transfer\\\\/i',
];
$textExtensions = ['css', 'env', 'html', 'js', 'json', 'md', 'php', 'ps1', 'txt', 'xml', 'yaml', 'yml'];

foreach ($trackedOutput as $path) {
    $absolute = $root.'/'.str_replace('\\', '/', $path);

    if (! is_file($absolute) || filesize($absolute) > 2_000_000 || ! in_array(strtolower(pathinfo($absolute, PATHINFO_EXTENSION)), $textExtensions, true)) {
        continue;
    }

    $contents = file_get_contents($absolute);

    if ($contents === false) {
        $errors[] = sprintf('Tracked text file could not be read: %s', $path);

        continue;
    }

    foreach ($secretPatterns as $pattern) {
        $pass(preg_match($pattern, $contents) !== 1, sprintf('Possible private path or credential found in tracked file: %s', $path));
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Release verification failed:\n\n - ".implode("\n - ", array_unique($errors))."\n");
    exit(1);
}

fwrite(STDOUT, sprintf(
    "Release verification passed: v%s, SG01-SG%02d, %d PNG files, %d checks.\n",
    $version,
    $groupCount,
    count($pngSignatures),
    $checks,
));
