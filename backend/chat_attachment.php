<?php


require_once __DIR__ . '/site_status.php';
vnzEnforceSiteMaintenance('json', true, true, 'messaging');
ob_start();
ini_set('display_errors', '0');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Cross-Origin-Resource-Policy: same-origin');

require_once __DIR__ . '/chat_service.php';
require_once __DIR__ . '/chat_attachments.php';

function chatAttachmentErrorResponse(int $status): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $status === 405 ? 'Method not allowed.' : 'Attachment unavailable.';
}

try {
    chatRequireMethod('GET');
    $actor = chatGetCurrentActor();
    $messageId = chatPositiveInteger(
        $_GET['message_id'] ?? null,
        'message ID'
    );

    global $pdo;
    $stmt = $pdo->prepare("
        SELECT
            m.conversation_id,
            m.attachment_path,
            m.attachment_name,
            m.attachment_size,
            m.message_type
        FROM messages m
        INNER JOIN conversation_participants p
            ON p.conversation_id = m.conversation_id
           AND p.actor_type = ?
           AND p.actor_id = ?
        WHERE m.id = ?
          AND (
              m.message_type IN ('image', 'file', 'voice', 'video')
              OR m.message_type = ''
          )
          AND m.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([
        $actor['actor_type'],
        $actor['actor_id'],
        $messageId
    ]);
    $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$attachment) {
        chatAttachmentErrorResponse(404);
        exit;
    }

    $attachment['message_type'] = chatEffectiveMessageType($attachment);
    if (!in_array($attachment['message_type'], ['image', 'file', 'voice', 'video'], true)) {
        chatAttachmentErrorResponse(404);
        exit;
    }

    chatRequireConversationMembership(
        (int) $attachment['conversation_id'],
        $actor
    );

    $storedName = (string) ($attachment['attachment_path'] ?? '');
    $validStoredName = match ($attachment['message_type']) {
        'image' => preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $storedName) === 1,
        'file' => preg_match('/\A[a-f0-9]{32}\.pdf\z/', $storedName) === 1,
        'voice' => preg_match('/\A[a-f0-9]{32}\.(?:ogg|webm)\z/', $storedName) === 1,
        'video' => preg_match('/\A[a-f0-9]{32}\.(?:mp4|webm)\z/', $storedName) === 1,
        default => false
    };
    if (!$validStoredName) {
        chatAttachmentErrorResponse(404);
        exit;
    }

    $directory = chatAttachmentStorageDirectory();
    $filePath = realpath($directory . DIRECTORY_SEPARATOR . $storedName);
    if (
        $filePath === false
        || dirname($filePath) !== $directory
        || !is_file($filePath)
        || !is_readable($filePath)
    ) {
        chatAttachmentErrorResponse(404);
        exit;
    }

    $mimeTypeByExtension = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf'
    ];
    if ($attachment['message_type'] === 'video') {
        $mimeTypeByExtension['mp4'] = 'video/mp4';
        $mimeTypeByExtension['webm'] = 'video/webm';
    }
    if ($attachment['message_type'] === 'voice') {
        $mimeTypeByExtension['ogg'] = 'audio/ogg';
        $mimeTypeByExtension['webm'] = 'audio/webm';
    }
    $extension = pathinfo($storedName, PATHINFO_EXTENSION);
    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($filePath);
    $detectedMimeType = $mimeType;
    if (
        $attachment['message_type'] === 'voice'
        && $extension === 'webm'
        && $mimeType === 'video/webm'
    ) {
        $detectedMimeType = 'audio/webm';
    }
    if (
        $attachment['message_type'] === 'voice'
        && $extension === 'ogg'
        && $mimeType === 'application/ogg'
    ) {
        $detectedMimeType = 'audio/ogg';
    }
    if (
        $attachment['message_type'] === 'video'
        && $extension === 'mp4'
        && $mimeType !== 'video/mp4'
    ) {
        chatAttachmentErrorResponse(404);
        exit;
    }
    if (
        !isset($mimeTypeByExtension[$extension])
        || $detectedMimeType !== $mimeTypeByExtension[$extension]
    ) {
        chatAttachmentErrorResponse(404);
        exit;
    }

    header('Content-Type: ' . $mimeTypeByExtension[$extension]);
    if ($attachment['message_type'] === 'file') {
        $downloadName = chatSanitizeAttachmentName(
            (string) ($attachment['attachment_name'] ?? ''),
            'pdf'
        );
        $asciiName = preg_replace('/[^A-Za-z0-9._ -]/', '_', $downloadName) ?: 'document.pdf';
        header(
            'Content-Disposition: attachment; filename="'
                . addcslashes($asciiName, '"\\')
                . '"; filename*=UTF-8\'\''
                . rawurlencode($downloadName)
        );
    } elseif ($attachment['message_type'] === 'image') {
        header('Content-Disposition: inline; filename="chat-image-' . $messageId . '.' . $extension . '"');
        header("Content-Security-Policy: default-src 'none'; sandbox");
    } elseif ($attachment['message_type'] === 'video') {
        header('Content-Disposition: inline; filename="chat-video-' . $messageId . '.' . $extension . '"');
    } else {
        header('Content-Disposition: inline; filename="voice-message-' . $messageId . '.' . $extension . '"');
    }
    header('Cache-Control: private, no-store');
    $fileSize = filesize($filePath);
    if ($fileSize === false) {
        chatAttachmentErrorResponse(404);
        exit;
    }
    $supportsRanges = in_array($attachment['message_type'], ['voice', 'video'], true);
    if ($supportsRanges) {
        header('Accept-Ranges: bytes');
    }
    $start = 0;
    $end = $fileSize - 1;
    $rangeHeader = $_SERVER['HTTP_RANGE'] ?? '';
    if ($supportsRanges && $rangeHeader !== '') {
        if (
            preg_match('/\Abytes=(\d*)-(\d*)\z/', $rangeHeader, $matches) !== 1
            || ($matches[1] === '' && $matches[2] === '')
        ) {
            header('Content-Range: bytes */' . $fileSize);
            chatAttachmentErrorResponse(416);
            exit;
        }
        if ($matches[1] === '') {
            $suffixLength = (int) $matches[2];
            if ($suffixLength < 1) {
                header('Content-Range: bytes */' . $fileSize);
                chatAttachmentErrorResponse(416);
                exit;
            }
            $start = max(0, $fileSize - $suffixLength);
        } else {
            $start = (int) $matches[1];
            $end = $matches[2] === '' ? $end : (int) $matches[2];
        }
        if ($start >= $fileSize || $end < $start) {
            header('Content-Range: bytes */' . $fileSize);
            chatAttachmentErrorResponse(416);
            exit;
        }
        $end = min($end, $fileSize - 1);
        http_response_code(206);
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
    }

    $contentLength = $end - $start + 1;
    header('Content-Length: ' . (string) $contentLength);
    $handle = fopen($filePath, 'rb');
    if ($handle === false || fseek($handle, $start) !== 0) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        chatAttachmentErrorResponse(404);
        exit;
    }
    $remaining = $contentLength;
    while ($remaining > 0 && !feof($handle)) {
        $chunk = fread($handle, min(8192, $remaining));
        if ($chunk === false || $chunk === '') {
            break;
        }
        echo $chunk;
        $remaining -= strlen($chunk);
    }
    fclose($handle);
} catch (ChatHttpException $e) {
    chatAttachmentErrorResponse($e->httpStatus === 405 ? 405 : 404);
} catch (Throwable $e) {
    error_log('Chat attachment delivery failed.');
    chatAttachmentErrorResponse(404);
}
