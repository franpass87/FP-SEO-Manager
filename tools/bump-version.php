#!/usr/bin/env php
<?php
declare(strict_types=1);

function error(string $message): void {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function findPluginFile(string $root): string {
    $candidates = glob($root . DIRECTORY_SEPARATOR . '*.php');
    if ($candidates === false) {
        error('Unable to scan plugin root for PHP files.');
    }

    foreach ($candidates as $candidate) {
        $handle = @fopen($candidate, 'rb');
        if ($handle === false) {
            continue;
        }

        $header = fread($handle, 8192) ?: '';
        fclose($handle);

        if (preg_match('/Plugin Name\s*:/i', (string) $header) === 1) {
            return $candidate;
        }
    }

    error('Unable to locate the main plugin file.');
}

function parseOptions(): array {
    $options = getopt('', [
        'set:',
        'set-version:',
        'bump:',
        'major',
        'minor',
        'patch',
    ]);

    if ($options === false) {
        error('Failed to parse options.');
    }

    $setVersion = $options['set'] ?? $options['set-version'] ?? null;

    if (is_array($setVersion)) {
        error('Multiple --set options provided.');
    }

    if (is_string($setVersion)) {
        $setVersion = trim($setVersion);
    }

    $bump = $options['bump'] ?? null;

    if (is_array($bump)) {
        error('Multiple --bump options provided.');
    }

    if ($bump !== null) {
        $bump = strtolower(trim((string) $bump));
    }

    foreach (['major', 'minor', 'patch'] as $key) {
        if (array_key_exists($key, $options)) {
            $bump = $key;
        }
    }

    if ($setVersion !== null && $setVersion === '') {
        error('Provided --set version is empty.');
    }

    if ($bump === null || $bump === '') {
        $bump = 'patch';
    }

    if (! in_array($bump, ['major', 'minor', 'patch'], true)) {
        error('Invalid bump type: ' . $bump);
    }

    return [$setVersion, $bump];
}

function normalizeVersion(string $version): string {
    if (! preg_match('/^\d+\.\d+\.\d+$/', $version)) {
        error('Version must follow semantic versioning (X.Y.Z).');
    }

    return $version;
}

function incrementVersion(string $version, string $type): string {
    [$major, $minor, $patch] = array_map('intval', explode('.', $version));

    switch ($type) {
        case 'major':
            $major++;
            $minor = 0;
            $patch = 0;
            break;
        case 'minor':
            $minor++;
            $patch = 0;
            break;
        case 'patch':
            $patch++;
            break;
        default:
            error('Unsupported bump type: ' . $type);
    }

    return sprintf('%d.%d.%d', $major, $minor, $patch);
}

[$setVersion, $bump] = parseOptions();
$root = dirname(__DIR__);
$pluginFile = findPluginFile($root);

$contents = file_get_contents($pluginFile);

if ($contents === false) {
    error('Unable to read plugin file: ' . $pluginFile);
}

$hasBom = str_starts_with($contents, "\u{FEFF}");

if ($hasBom) {
    $contents = substr($contents, 3);
}

$lineEnding = str_contains($contents, "\r\n") ? "\r\n" : "\n";

if (! preg_match('/^(?<prefix>\s*\*\s*Version:\s*)(?<version>\d+\.\d+\.\d+)(?<suffix>.*)$/m', $contents, $matches, PREG_OFFSET_CAPTURE)) {
    error('Unable to find Version header in plugin file: ' . $pluginFile);
}

$currentVersion = $matches['version'][0];
$newVersion = $setVersion !== null ? normalizeVersion($setVersion) : incrementVersion($currentVersion, $bump);

if ($newVersion === $currentVersion) {
    echo $newVersion . PHP_EOL;
    exit(0);
}

$replacement = $matches['prefix'][0] . $newVersion . $matches['suffix'][0];
$start = $matches[0][1];
$length = strlen($matches[0][0]);

$updated = substr_replace($contents, $replacement, $start, $length);

if ($hasBom) {
    $updated = "\u{FEFF}" . $updated;
}

$updated = str_replace(["\r\n", "\n"], $lineEnding, $updated);

if (file_put_contents($pluginFile, $updated) === false) {
    error('Unable to write updated version to plugin file.');
}

echo $newVersion . PHP_EOL;
