<?php

class ChatHttpException extends RuntimeException
{
    public int $httpStatus;

    public function __construct(string $message, int $httpStatus)
    {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
    }
}

class CallInviteTestPdo extends PDO
{
    public array $calls = [];
    private array $transactionSnapshot = [];
    private bool $transactionActive = false;

    public function __construct()
    {
    }

    public function beginTransaction(): bool
    {
        $this->transactionSnapshot = $this->calls;
        $this->transactionActive = true;
        return true;
    }

    public function commit(): bool
    {
        $this->transactionActive = false;
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->transactionActive;
    }

    public function rollBack(): bool
    {
        $this->calls = $this->transactionSnapshot;
        $this->transactionActive = false;
        return true;
    }

    public function prepare(
        string $query,
        array $options = []
    ): PDOStatement|false {
        return new CallInviteTestStatement($this, $query);
    }
}

class CallInviteTestStatement extends PDOStatement
{
    private array|false $row = false;
    private int $affectedRows = 0;

    public function __construct(
        private CallInviteTestPdo $pdo,
        private string $query
    ) {
    }

    public function execute(?array $params = null): bool
    {
        $params ??= [];
        if (str_contains($this->query, 'INSERT IGNORE INTO chat_call_sessions')) {
            $callId = (string) $params[0];
            if (!isset($this->pdo->calls[$callId])) {
                $this->pdo->calls[$callId] = [
                    'call_id' => $callId,
                    'conversation_id' => (int) $params[1],
                    'caller_type' => (string) $params[2],
                    'caller_id' => (int) $params[3],
                    'callee_type' => (string) $params[4],
                    'callee_id' => (int) $params[5],
                    'call_mode' => (string) $params[6],
                    'status' => 'ringing',
                    'invite_is_fresh' => true
                ];
                $this->affectedRows = 1;
            }
            return true;
        }

        if (str_contains($this->query, 'FROM chat_call_sessions')) {
            $this->row = $this->pdo->calls[(string) $params[0]] ?? false;
            return true;
        }

        throw new RuntimeException('Unexpected SQL in call invite test.');
    }

    public function rowCount(): int
    {
        return $this->affectedRows;
    }

    public function fetch(
        int $mode = PDO::FETCH_DEFAULT,
        int $cursorOrientation = PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0
    ): mixed {
        return $this->row;
    }
}

require_once __DIR__ . '/../backend/chat_call_sessions.php';

function assertCallInviteTest(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expectUnavailableCallInvite(
    CallInviteTestPdo $pdo,
    array $actor,
    array $peer,
    array $event,
    int $expectedStatus = 409
): void {
    try {
        chatPersistCallEvent(42, $actor, $peer, $event);
    } catch (ChatHttpException $exception) {
        assertCallInviteTest(
            $exception->httpStatus === $expectedStatus,
            'An ineligible invite should return HTTP '
                . $expectedStatus
                . '; got '
                . $exception->httpStatus
                . '.'
        );
        return;
    }

    throw new RuntimeException('An ineligible invite was accepted.');
}

$pdo = new CallInviteTestPdo();
$caller = ['actor_type' => 'admin', 'actor_id' => 7];
$callee = ['actor_type' => 'seller', 'actor_id' => 19];
$event = [
    'event' => 'call.invite',
    'call_id' => 'd09c6518-e0d6-4f53-a04c-8750c2046f3b',
    'mode' => 'audio'
];

$firstAttempt = chatPersistCallEvent(42, $caller, $callee, $event);
assertCallInviteTest(!$firstAttempt['duplicate'], 'The first invite should create one call session.');
assertCallInviteTest(count($pdo->calls) === 1, 'The first invite should persist exactly one call session.');

$publishAttempts = 0;
$publish = static function () use (&$publishAttempts): string {
    $publishAttempts++;
    return $publishAttempts === 1 ? 'unavailable' : 'published';
};
$firstDelivery = $publish();
assertCallInviteTest($firstDelivery !== 'published', 'The simulated first publish should fail.');

$retry = chatPersistCallEvent(42, $caller, $callee, $event);
assertCallInviteTest($retry['duplicate'], 'Retrying the same call ID should not create another session.');
assertCallInviteTest(
    chatCallSignalShouldPublishDuplicate($retry),
    'A matching, fresh ringing invite retry should be relayed.'
);
$retryDelivery = chatCallSignalShouldPublishDuplicate($retry) ? $publish() : 'skipped';
assertCallInviteTest($retryDelivery === 'published', 'The retry should successfully publish the invite.');
assertCallInviteTest($publishAttempts === 2, 'The invite should be published once per attempt.');
assertCallInviteTest(count($pdo->calls) === 1, 'Retrying must not create a duplicate call session.');

$wrongModeEvent = $event;
$wrongModeEvent['mode'] = 'video';
expectUnavailableCallInvite($pdo, $caller, $callee, $wrongModeEvent);

$pdo->calls[$event['call_id']]['invite_is_fresh'] = false;
expectUnavailableCallInvite($pdo, $caller, $callee, $event);
$pdo->calls[$event['call_id']]['invite_is_fresh'] = true;
foreach (['accepted', 'completed', 'missed', 'declined', 'cancelled', 'busy', 'failed'] as $status) {
    $pdo->calls[$event['call_id']]['status'] = $status;
    expectUnavailableCallInvite($pdo, $caller, $callee, $event);
}
$pdo->calls[$event['call_id']]['status'] = 'ringing';
expectUnavailableCallInvite(
    $pdo,
    ['actor_type' => 'admin', 'actor_id' => 8],
    $callee,
    $event,
    404
);

echo "Call invite retry regression tests passed.\n";
