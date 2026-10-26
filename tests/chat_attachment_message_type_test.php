<?php

require_once __DIR__ . '/../backend/chat_attachments.php';

function assertChatAttachmentType(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$legacyMp4 = [
    'message_type' => '',
    'attachment_path' => str_repeat('a', 32) . '.mp4',
    'attachment_size' => 1024
];
assertChatAttachmentType(
    chatEffectiveMessageType($legacyMp4) === 'video',
    'A legacy blank message type with a valid MP4 attachment should be treated as video.'
);

$legacyWebm = [
    'message_type' => '',
    'attachment_path' => str_repeat('b', 32) . '.webm',
    'attachment_size' => 1024
];
assertChatAttachmentType(
    chatEffectiveMessageType($legacyWebm) === 'video',
    'A legacy blank message type with a valid WebM attachment should be treated as video.'
);

assertChatAttachmentType(
    chatEffectiveMessageType([
        'message_type' => 'image',
        'attachment_path' => str_repeat('c', 32) . '.mp4',
        'attachment_size' => 1024
    ]) === 'image',
    'A known message type must not be overridden based on its attachment extension.'
);

assertChatAttachmentType(
    chatEffectiveMessageType([
        'message_type' => '',
        'attachment_path' => '../untrusted.mp4',
        'attachment_size' => 1024
    ]) === '',
    'An invalid attachment path must not be inferred as a video.'
);

assertChatAttachmentType(
    chatEffectiveMessageType([
        'message_type' => '',
        'attachment_path' => str_repeat('d', 32) . '.mp4',
        'attachment_size' => 0
    ]) === '',
    'An empty attachment must not be inferred as a video.'
);

echo "Chat attachment message type tests passed." . PHP_EOL;
