<?php

function chatAttachmentMaxBytes(): int
{
    $configured = getenv('CHAT_ATTACHMENT_MAX_BYTES');
    if ($configured === false || $configured === '') {
        return 5 * 1024 * 1024;
    }

    $limit = filter_var($configured, FILTER_VALIDATE_INT);
    if ($limit === false || $limit < 1 || $limit > 20 * 1024 * 1024) {
        throw new RuntimeException('Invalid chat attachment size configuration.');
    }

    return $limit;
}

function chatDocumentMaxBytes(): int
{
    $configured = getenv('CHAT_DOCUMENT_MAX_BYTES');
    if ($configured === false || $configured === '') {
        return 10 * 1024 * 1024;
    }

    $limit = filter_var($configured, FILTER_VALIDATE_INT);
    if ($limit === false || $limit < 1 || $limit > 50 * 1024 * 1024) {
        throw new RuntimeException('Invalid chat document size configuration.');
    }

    return $limit;
}

function chatVoiceMaxBytes(): int
{
    $configured = getenv('CHAT_VOICE_MAX_BYTES');
    if ($configured === false || $configured === '') {
        return 5 * 1024 * 1024;
    }

    $limit = filter_var($configured, FILTER_VALIDATE_INT);
    if ($limit === false || $limit < 1 || $limit > 20 * 1024 * 1024) {
        throw new RuntimeException('Invalid chat voice size configuration.');
    }

    return $limit;
}

function chatVoiceMaxDurationSeconds(): int
{
    $configured = getenv('CHAT_VOICE_MAX_DURATION_SECONDS');
    if ($configured === false || $configured === '') {
        return 120;
    }

    $limit = filter_var($configured, FILTER_VALIDATE_INT);
    if ($limit === false || $limit < 1 || $limit > 300) {
        throw new RuntimeException('Invalid chat voice duration configuration.');
    }

    return $limit;
}

function chatIniSizeLimitBytes(string $directive, bool $zeroMeansUnlimited): ?int
{
    $configured = ini_get($directive);
    if (
        $configured === false
        || preg_match('/\A([0-9]+)\s*([KMG]?)\z/i', trim($configured), $matches) !== 1
    ) {
        throw new RuntimeException('Invalid PHP upload size configuration.');
    }

    $size = (int) $matches[1];
    if ($size === 0 && $zeroMeansUnlimited) {
        return null;
    }

    $multiplier = match (strtoupper($matches[2])) {
        'K' => 1024,
        'M' => 1024 * 1024,
        'G' => 1024 * 1024 * 1024,
        default => 1
    };

    return $size > intdiv(PHP_INT_MAX, $multiplier)
        ? PHP_INT_MAX
        : $size * $multiplier;
}

function chatVideoMaxBytes(): int
{
    $configured = getenv('CHAT_VIDEO_MAX_BYTES');
    if ($configured === false || $configured === '') {
        $limit = 50 * 1024 * 1024;
    } else {
        $limit = filter_var($configured, FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > 200 * 1024 * 1024) {
            throw new RuntimeException('Invalid chat video size configuration.');
        }
    }

    $uploadLimit = chatIniSizeLimitBytes('upload_max_filesize', false);
    $postLimit = chatIniSizeLimitBytes('post_max_size', true);
    $limit = min($limit, $uploadLimit ?? PHP_INT_MAX);
    if ($postLimit !== null) {
        $limit = min($limit, max(0, $postLimit - 1024 * 1024));
    }

    return $limit;
}

function chatEffectiveMessageType(array $message): string
{
    $messageType = (string) ($message['message_type'] ?? '');
    if ($messageType !== '') {
        return $messageType;
    }

    $attachmentPath = (string) ($message['attachment_path'] ?? '');
    $attachmentSize = (int) ($message['attachment_size'] ?? 0);
    if (
        $attachmentSize > 0
        && preg_match('/\A[a-f0-9]{32}\.(?:mp4|webm)\z/', $attachmentPath) === 1
    ) {
        return 'video';
    }

    return $messageType;
}

function chatSanitizeAttachmentName(string $name, string $extension): string
{
    $name = str_replace('\\', '/', $name);
    $name = basename($name);
    $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
    $name = preg_replace('/[^\pL\pN._ -]+/u', '_', $name) ?? '';
    $name = trim($name, " .\t\n\r\0\x0B");
    $name = preg_replace('/\.(?:jpe?g|png|webp|pdf|ogg|webm|mp4)\z/i', '', $name) ?? $name;
    $name = trim(mb_substr($name, 0, 180, 'UTF-8'), " .");

    return ($name !== '' ? $name : 'attachment') . '.' . $extension;
}

function chatAttachmentStorageDirectory(): string
{
    $configured = getenv('CHAT_ATTACHMENT_DIRECTORY');
    $directory = $configured !== false && trim($configured) !== ''
        ? trim($configured)
        : __DIR__ . '/../uploads/chat_attachments';

    $isAbsolute = DIRECTORY_SEPARATOR === '\\'
        ? preg_match('/\A(?:[A-Za-z]:\\\\|\\\\\\\\)/', $directory) === 1
        : str_starts_with($directory, '/');
    if (!$isAbsolute) {
        throw new RuntimeException('Chat attachment directory must be absolute.');
    }

    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to prepare chat attachment storage.');
    }

    $resolved = realpath($directory);
    if ($resolved === false || !is_dir($resolved)) {
        throw new RuntimeException('Chat attachment storage is unavailable.');
    }

    return $resolved;
}

function chatStoreVideoAttachment(array $file): array
{
    if (!isset($file['error']) || !is_int($file['error'])) {
        throw new ChatHttpException('The selected video upload is invalid.', 422);
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = $file['error'] === UPLOAD_ERR_INI_SIZE
            || $file['error'] === UPLOAD_ERR_FORM_SIZE
            ? 'The video exceeds the upload size limit.'
            : 'The video could not be uploaded. Please try again.';
        throw new ChatHttpException($message, 422);
    }

    $temporaryPath = $file['tmp_name'] ?? null;
    if (
        !is_string($temporaryPath)
        || $temporaryPath === ''
        || !is_uploaded_file($temporaryPath)
    ) {
        throw new ChatHttpException('The selected video upload is invalid.', 422);
    }

    $size = filesize($temporaryPath);
    $maxBytes = chatVideoMaxBytes();
    if ($size === false || $size < 1 || $size > $maxBytes) {
        throw new ChatHttpException(
            'Videos must be no larger than '
                . number_format($maxBytes / 1024 / 1024, 1)
                . ' MB.',
            422
        );
    }
    if (!class_exists('finfo')) {
        throw new ChatHttpException(
            'Video uploads are unavailable on this server.',
            503
        );
    }

    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
    $handle = fopen($temporaryPath, 'rb');
    if ($handle === false) {
        throw new ChatHttpException('The selected video upload is invalid.', 422);
    }
    $header = fread($handle, 16);
    fclose($handle);
    if (!is_string($header)) {
        throw new ChatHttpException('The selected video upload is invalid.', 422);
    }

    $extension = null;
    if (
        $mimeType === 'video/mp4'
        && strlen($header) >= 12
        && substr($header, 4, 4) === 'ftyp'
    ) {
        $extension = 'mp4';
    } elseif (
        $mimeType === 'video/webm'
        && str_starts_with($header, "\x1A\x45\xDF\xA3")
    ) {
        $extension = 'webm';
    }
    if ($extension === null) {
        throw new ChatHttpException('Choose a valid MP4 or WebM video.', 422);
    }

    $directory = chatAttachmentStorageDirectory();
    $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
    $storedPath = $directory . DIRECTORY_SEPARATOR . $storedName;
    if (!move_uploaded_file($temporaryPath, $storedPath)) {
        throw new RuntimeException('Unable to save the uploaded video.');
    }
    chmod($storedPath, 0640);

    return [
        'path' => $storedName,
        'absolute_path' => $storedPath,
        'name' => chatSanitizeAttachmentName(
            (string) ($file['name'] ?? ''),
            $extension
        ),
        'size' => (int) $size
    ];
}

function chatStoreImageAttachment(array $file): array
{
    if (!isset($file['error']) || !is_int($file['error'])) {
        throw new ChatHttpException('The selected image upload is invalid.', 422);
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = $file['error'] === UPLOAD_ERR_INI_SIZE
            || $file['error'] === UPLOAD_ERR_FORM_SIZE
            ? 'The image exceeds the upload size limit.'
            : 'The image could not be uploaded. Please try again.';
        throw new ChatHttpException($message, 422);
    }

    $temporaryPath = $file['tmp_name'] ?? null;
    if (
        !is_string($temporaryPath)
        || $temporaryPath === ''
        || !is_uploaded_file($temporaryPath)
    ) {
        throw new ChatHttpException('The selected image upload is invalid.', 422);
    }

    $size = filesize($temporaryPath);
    $maxBytes = chatAttachmentMaxBytes();
    if ($size === false || $size < 1 || $size > $maxBytes) {
        throw new ChatHttpException(
            'Images must be no larger than '
                . number_format($maxBytes / 1024 / 1024, 1)
                . ' MB.',
            422
        );
    }

    if (!class_exists('finfo') || !function_exists('imagecreatefromstring')) {
        throw new ChatHttpException(
            'Image uploads require the PHP GD extension on this server.',
            503
        );
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($temporaryPath);
    $imageInfo = @getimagesize($temporaryPath);
    $allowedTypes = [
        'image/jpeg' => ['extension' => 'jpg', 'type' => IMAGETYPE_JPEG],
        'image/png' => ['extension' => 'png', 'type' => IMAGETYPE_PNG],
        'image/webp' => ['extension' => 'webp', 'type' => IMAGETYPE_WEBP]
    ];
    if (
        !is_string($mimeType)
        || !isset($allowedTypes[$mimeType])
        || $imageInfo === false
        || ($imageInfo['mime'] ?? null) !== $mimeType
        || (int) ($imageInfo[2] ?? 0) !== $allowedTypes[$mimeType]['type']
    ) {
        throw new ChatHttpException(
            'Choose a valid JPEG, PNG, or WebP image.',
            422
        );
    }

    $width = (int) $imageInfo[0];
    $height = (int) $imageInfo[1];
    if (
        $width < 1
        || $height < 1
        || $width > 8000
        || $height > 8000
        || $width * $height > 12000000
    ) {
        throw new ChatHttpException(
            'The image dimensions are too large to process.',
            422
        );
    }

    $imageBytes = file_get_contents($temporaryPath);
    $image = $imageBytes === false ? false : @imagecreatefromstring($imageBytes);
    unset($imageBytes);
    if ($image === false) {
        throw new ChatHttpException('The uploaded file is not a decodable image.', 422);
    }

    $directory = chatAttachmentStorageDirectory();
    $encodedTemporaryPath = tempnam($directory, '.chat-image-');
    if ($encodedTemporaryPath === false) {
        imagedestroy($image);
        throw new RuntimeException('Unable to prepare image storage.');
    }

    $encoded = match ($mimeType) {
        'image/jpeg' => imagejpeg($image, $encodedTemporaryPath, 86),
        'image/png' => imagepng($image, $encodedTemporaryPath, 6),
        'image/webp' => function_exists('imagewebp')
            ? imagewebp($image, $encodedTemporaryPath, 84)
            : false
    };
    imagedestroy($image);

    if (!$encoded) {
        @unlink($encodedTemporaryPath);
        throw new ChatHttpException(
            'This image format is unavailable on the server.',
            503
        );
    }

    $encodedSize = filesize($encodedTemporaryPath);
    $encodedMimeType = (new finfo(FILEINFO_MIME_TYPE))->file($encodedTemporaryPath);
    if ($encodedSize === false || $encodedSize > $maxBytes || $encodedMimeType !== $mimeType) {
        @unlink($encodedTemporaryPath);
        throw new ChatHttpException(
            'The processed image exceeds the upload limit or is invalid.',
            422
        );
    }

    try {
        $storedName = bin2hex(random_bytes(16))
            . '.'
            . $allowedTypes[$mimeType]['extension'];
        $storedPath = $directory . DIRECTORY_SEPARATOR . $storedName;
        if (!rename($encodedTemporaryPath, $storedPath)) {
            throw new RuntimeException('Unable to save the uploaded image.');
        }
    } catch (Throwable $e) {
        @unlink($encodedTemporaryPath);
        throw $e;
    }

    return [
        'path' => $storedName,
        'absolute_path' => $storedPath,
        'name' => chatSanitizeAttachmentName(
            (string) ($file['name'] ?? ''),
            $allowedTypes[$mimeType]['extension']
        ),
        'size' => (int) $encodedSize
    ];
}

function chatStorePdfAttachment(array $file): array
{
    if (!isset($file['error']) || !is_int($file['error'])) {
        throw new ChatHttpException('The selected PDF upload is invalid.', 422);
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = $file['error'] === UPLOAD_ERR_INI_SIZE
            || $file['error'] === UPLOAD_ERR_FORM_SIZE
            ? 'The PDF exceeds the upload size limit.'
            : 'The PDF could not be uploaded. Please try again.';
        throw new ChatHttpException($message, 422);
    }

    $temporaryPath = $file['tmp_name'] ?? null;
    if (
        !is_string($temporaryPath)
        || $temporaryPath === ''
        || !is_uploaded_file($temporaryPath)
    ) {
        throw new ChatHttpException('The selected PDF upload is invalid.', 422);
    }

    $size = filesize($temporaryPath);
    if ($size === false || $size < 1 || $size > chatDocumentMaxBytes()) {
        throw new ChatHttpException(
            'PDF files must be no larger than '
                . number_format(chatDocumentMaxBytes() / 1024 / 1024, 1)
                . ' MB.',
            422
        );
    }

    if (!class_exists('finfo')) {
        throw new ChatHttpException(
            'PDF uploads are unavailable on this server.',
            503
        );
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    if ($finfo->file($temporaryPath) !== 'application/pdf') {
        throw new ChatHttpException('Choose a valid PDF document.', 422);
    }

    $handle = fopen($temporaryPath, 'rb');
    if ($handle === false) {
        throw new ChatHttpException('The selected PDF upload is invalid.', 422);
    }
    $signature = fread($handle, 5);
    fseek($handle, -min((int) $size, 2048), SEEK_END);
    $trailer = stream_get_contents($handle);
    fclose($handle);
    if (
        $signature !== '%PDF-'
        || !is_string($trailer)
        || !str_contains($trailer, '%%EOF')
    ) {
        throw new ChatHttpException('The selected file is not a valid PDF.', 422);
    }

    $directory = chatAttachmentStorageDirectory();
    $storedName = bin2hex(random_bytes(16)) . '.pdf';
    $storedPath = $directory . DIRECTORY_SEPARATOR . $storedName;
    if (!move_uploaded_file($temporaryPath, $storedPath)) {
        throw new RuntimeException('Unable to save the uploaded PDF.');
    }
    chmod($storedPath, 0640);

    return [
        'path' => $storedName,
        'absolute_path' => $storedPath,
        'name' => chatSanitizeAttachmentName(
            (string) ($file['name'] ?? ''),
            'pdf'
        ),
        'size' => (int) $size
    ];
}

function chatStoreVoiceAttachment(array $file): array
{
    if (!isset($file['error']) || !is_int($file['error'])) {
        throw new ChatHttpException('The selected voice upload is invalid.', 422);
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = $file['error'] === UPLOAD_ERR_INI_SIZE
            || $file['error'] === UPLOAD_ERR_FORM_SIZE
            ? 'Voice messages must be no larger than '
                . number_format(chatVoiceMaxBytes() / 1024 / 1024, 1)
                . ' MB.'
            : 'The voice message could not be uploaded. Please try again.';
        throw new ChatHttpException($message, 422);
    }

    $temporaryPath = $file['tmp_name'] ?? null;
    if (
        !is_string($temporaryPath)
        || $temporaryPath === ''
        || !is_uploaded_file($temporaryPath)
    ) {
        throw new ChatHttpException('The selected voice upload is invalid.', 422);
    }

    $size = filesize($temporaryPath);
    $maxBytes = chatVoiceMaxBytes();
    if ($size === false || $size < 1 || $size > $maxBytes) {
        throw new ChatHttpException(
            'Voice messages must be no larger than '
                . number_format($maxBytes / 1024 / 1024, 1)
                . ' MB.',
            422
        );
    }
    if (!class_exists('finfo')) {
        throw new ChatHttpException(
            'Voice messages are unavailable on this server.',
            503
        );
    }

    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
    $handle = fopen($temporaryPath, 'rb');
    if ($handle === false) {
        throw new ChatHttpException('The selected voice upload is invalid.', 422);
    }

    $header = fread($handle, min((int) $size, 1024 * 1024));
    fclose($handle);
    if (!is_string($header)) {
        throw new ChatHttpException('The selected voice upload is invalid.', 422);
    }

    $formats = [
        'webm' => [
            'mime_types' => ['audio/webm', 'video/webm'],
            'signature' => str_starts_with($header, "\x1A\x45\xDF\xA3")
                && str_contains($header, 'A_OPUS')
        ],
        'ogg' => [
            'mime_types' => ['audio/ogg', 'application/ogg'],
            'signature' => str_starts_with($header, 'OggS')
                && str_contains($header, 'OpusHead')
        ]
    ];
    $extension = null;
    foreach ($formats as $candidateExtension => $format) {
        if (
            in_array($mimeType, $format['mime_types'], true)
            && $format['signature']
        ) {
            $extension = $candidateExtension;
            break;
        }
    }
    if ($extension === null) {
        throw new ChatHttpException(
            'This recording format is not supported. Record audio as Opus in WebM or Ogg.',
            422
        );
    }

    $directory = chatAttachmentStorageDirectory();
    $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
    $storedPath = $directory . DIRECTORY_SEPARATOR . $storedName;
    if (!move_uploaded_file($temporaryPath, $storedPath)) {
        throw new RuntimeException('Unable to save the uploaded voice message.');
    }
    chmod($storedPath, 0640);

    return [
        'path' => $storedName,
        'absolute_path' => $storedPath,
        'name' => 'voice-message.' . $extension,
        'size' => (int) $size
    ];
}
