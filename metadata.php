<?php

// declare(strict_types=1);

function getMetadataFilePath(): string
{
    return __DIR__ . '/images/metadata.json';
}

function loadImageMetadata(): array
{
    $metadataFile = getMetadataFilePath();
    if (!is_file($metadataFile)) {
        return [];
    }

    $json = file_get_contents($metadataFile);
    if ($json === false) {
        return [];
    }

    $data = json_decode($json, true);
    return is_array($data) ? array_filter($data, static function ($item) {
        return is_array($item) && isset($item['title']) && isset($item['description']);
    }) : [];
}

function saveImageMetadata(array $metadata): bool
{
    $metadataFile = getMetadataFilePath();
    $json = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    return file_put_contents($metadataFile, $json) !== false;
}

function formatBytes(int $size): string
{
    if ($size >= 1073741824) {
        return number_format($size / 1073741824, 2) . ' GB';
    }
    if ($size >= 1048576) {
        return number_format($size / 1048576, 2) . ' MB';
    }
    if ($size >= 1024) {
        return number_format($size / 1024, 2) . ' KB';
    }
    return $size . ' B';
}

function formatExifValue($value): string
{
    if (is_array($value)) {
        return implode(', ', array_map('formatExifValue', $value));
    }
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    return (string)$value;
}

function isValidImageExtension(string $ext): bool
{
    return in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
}

function isValidImageMimeType(string $mime): bool
{
    return in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);
}

function normalizeMetadataValue(?string $value): string
{
    return trim((string)$value);
}
