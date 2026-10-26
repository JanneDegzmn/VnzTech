<?php

function globalChatModerationAppliesToConversationType(string $conversationType): bool
{
    return $conversationType === 'global';
}

function globalChatModerationSchemaAvailable(PDO $pdo): bool
{
    static $isAvailable = null;
    if ($isAvailable !== null) {
        return $isAvailable;
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME IN (
              'global_chat_moderation_rules',
              'global_chat_moderation_cases'
          )
    ");
    $stmt->execute();
    $isAvailable = (int) $stmt->fetchColumn() === 2;

    return $isAvailable;
}

function globalChatModerationNormalize(string $value): string
{
    $value = mb_strtolower($value, 'UTF-8');
    $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value);
    if (!is_string($value)) {
        throw new RuntimeException('Unable to normalize Global Chat moderation text.');
    }

    return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
}

function globalChatModerationRuleMatches(string $content, array $rule): bool
{
    $normalizedContent = globalChatModerationNormalize($content);
    $normalizedRule = globalChatModerationNormalize((string) ($rule['rule_text'] ?? ''));
    if ($normalizedContent === '' || $normalizedRule === '') {
        return false;
    }

    if (($rule['match_type'] ?? '') === 'word') {
        return in_array($normalizedRule, explode(' ', $normalizedContent), true);
    }
    if (($rule['match_type'] ?? '') !== 'phrase') {
        throw new InvalidArgumentException('Unsupported Global Chat moderation match type.');
    }

    $pattern = '/(?:^|\s)' . preg_quote($normalizedRule, '/') . '(?:$|\s)/u';
    $matched = preg_match($pattern, $normalizedContent);
    if ($matched === false) {
        throw new RuntimeException('Unable to evaluate Global Chat moderation rule.');
    }

    return $matched === 1;
}

function globalChatModerationMatches(string $content, array $rules): array
{
    $matches = [];
    foreach ($rules as $rule) {
        if (!empty($rule['enabled']) && globalChatModerationRuleMatches($content, $rule)) {
            $matches[] = $rule;
        }
    }

    return $matches;
}

function globalChatModerationLoadMatches(PDO $pdo, string $content): array
{
    if (!globalChatModerationSchemaAvailable($pdo)) {
        throw new ChatHttpException(
            'Global Chat moderation is not configured. Apply its database migration before sending Global Chat messages.',
            503
        );
    }
    $stmt = $pdo->query("
        SELECT id, rule_text, match_type, category, severity, action, enabled
        FROM global_chat_moderation_rules
        WHERE enabled = 1
        ORDER BY id
    ");

    return globalChatModerationMatches(
        $content,
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );
}

function globalChatModerationRecordReviewCases(
    PDO $pdo,
    int $messageId,
    string $content,
    array $actor,
    array $matches
): void {
    $stmt = $pdo->prepare("
        INSERT INTO global_chat_moderation_cases (
            message_id,
            rule_id,
            matched_rule,
            category,
            severity,
            action,
            status,
            message_snapshot,
            sender_type,
            sender_id
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($matches as $match) {
        if (($match['action'] ?? '') !== 'review') {
            continue;
        }
        $stmt->execute([
            $messageId,
            (int) $match['id'],
            (string) $match['rule_text'],
            (string) $match['category'],
            (string) $match['severity'],
            'review',
            'pending',
            $content,
            $actor['actor_type'],
            (int) $actor['actor_id']
        ]);
    }
}
