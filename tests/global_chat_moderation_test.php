<?php

require_once __DIR__ . '/../backend/global_chat_moderation_service.php';

function assertGlobalModeration(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$rules = [
    ['id' => 1, 'rule_text' => 'gago', 'match_type' => 'word', 'enabled' => 1, 'action' => 'block'],
    ['id' => 2, 'rule_text' => 'scammer', 'match_type' => 'word', 'enabled' => 1, 'action' => 'review'],
    ['id' => 3, 'rule_text' => 'wag kayong bumili', 'match_type' => 'phrase', 'enabled' => 1, 'action' => 'review'],
    ['id' => 4, 'rule_text' => 'send your password', 'match_type' => 'phrase', 'enabled' => 1, 'action' => 'block'],
    ['id' => 5, 'rule_text' => 'fuck', 'match_type' => 'word', 'enabled' => 0, 'action' => 'block'],
    ['id' => 6, 'rule_text' => 'scam sila', 'match_type' => 'phrase', 'enabled' => 1, 'action' => 'review'],
    ['id' => 7, 'rule_text' => 'fuckyou', 'match_type' => 'word', 'enabled' => 1, 'action' => 'block']
];

$allowedMessages = [
    'Gusto ko bumili ng broken phone.',
    'Magkano bili ninyo sa phone?',
    'May offer ba kayo sa motherboard?',
    'Buyer ako, saan puwedeng mag-submit?',
    'Magkano presyo ng board?',
    'May mas mataas na offer ba kayo?',
    'Saan puwedeng magbenta ng sirang phone?',
    'This is a scammery example without the whole word.'
];
foreach ($allowedMessages as $message) {
    assertGlobalModeration(
        globalChatModerationMatches($message, $rules) === [],
        'Ordinary business language or a partial word must not match: ' . $message
    );
}

$defamationMessage = 'WAG, kayong bumili sa VNZ TECH, scam sila.';
$defamationMatches = globalChatModerationMatches($defamationMessage, $rules);
assertGlobalModeration(
    count($defamationMatches) === 2
        && array_column($defamationMatches, 'action') === ['review', 'review']
        && array_column($defamationMatches, 'rule_text') === ['wag kayong bumili', 'scam sila'],
    'Complete accusation phrases must be flagged for review.'
);
$scammerMatches = globalChatModerationMatches(
    'Scammer ang may-ari ng VNZ TECH.',
    $rules
);
assertGlobalModeration(
    count($scammerMatches) === 1
        && $scammerMatches[0]['action'] === 'review',
    'Potential business accusations must be flagged for Admin review, not blocked.'
);
assertGlobalModeration(
    globalChatModerationMatches('You are a gago.', $rules)[0]['action'] === 'block',
    'An enabled configured blocking rule must match a whole prohibited word.'
);
assertGlobalModeration(
    globalChatModerationMatches('fuckyou', $rules)[0]['action'] === 'block',
    'The configured compound profanity rule must match as a whole word.'
);
assertGlobalModeration(
    globalChatModerationMatches('This is fucking allowed while its rule is disabled.', $rules) === [],
    'Disabled rules must not be applied.'
);

assertGlobalModeration(
    globalChatModerationRuleMatches('GAGO!!!', $rules[0]),
    'Whole-word matching must ignore capitalization and punctuation.'
);
assertGlobalModeration(
    !globalChatModerationRuleMatches('gagong seller', $rules[0]),
    'Whole-word rules must not match inside a longer word.'
);
assertGlobalModeration(
    globalChatModerationRuleMatches(
        'Please SEND your password.',
        $rules[3]
    ),
    'Complete phrase matching must normalize capitalization and punctuation.'
);
assertGlobalModeration(
    !globalChatModerationRuleMatches(
        'I am buying a phone from another buyer.',
        ['rule_text' => 'buy', 'match_type' => 'word']
    ),
    'The word buy must not match within buyer.'
);
assertGlobalModeration(
    globalChatModerationAppliesToConversationType('global'),
    'Moderation must apply to the Global Chat conversation type.'
);
assertGlobalModeration(
    !globalChatModerationAppliesToConversationType('private')
        && !globalChatModerationAppliesToConversationType('transaction'),
    'Private and transaction chats must be isolated from Global Chat moderation.'
);

echo "Global Chat moderation tests passed." . PHP_EOL;
