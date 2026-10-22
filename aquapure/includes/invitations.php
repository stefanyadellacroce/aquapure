<?php
declare(strict_types=1);

function normalize_invitation_token(string $token): string
{
    return strtoupper((string) preg_replace('/[^a-z0-9-]/i', '', trim($token)));
}

function invitation_token_hash(string $token): string
{
    return hash('sha256', normalize_invitation_token($token));
}

function expire_registration_invitations(PDO $pdo): void
{
    $pdo->exec(
        "UPDATE user_invitations
         SET status = 'Expired'
         WHERE status = 'Active' AND expires_at <= NOW()",
    );
}

/**
 * @return array{token:string,expires_at:string,role:string,email:string}
 */
function create_registration_invitation(
    PDO $pdo,
    string $role,
    string $email,
    int $createdBy,
    int $expiresInHours,
): array {
    if (!in_array($role, ['Admin', 'Staff'], true)) {
        throw new InvalidArgumentException('Invalid invitation role.');
    }
    if (!in_array($expiresInHours, [24, 48, 72], true)) {
        throw new InvalidArgumentException('Invitation expiry must be 24, 48, or 72 hours.');
    }

    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
        throw new InvalidArgumentException('Enter a valid invitation email address.');
    }

    // Defense in depth: invitation creation must belong to a currently active Admin,
    // even if a future UI accidentally exposes this helper to another role.
    $creator = $pdo->prepare(
        "SELECT user_id FROM users
         WHERE user_id = ? AND role = 'Admin' AND account_status = 'Active'
         LIMIT 1",
    );
    $creator->execute([$createdBy]);
    if (!$creator->fetchColumn()) {
        throw new RuntimeException('Only an active Administrator can create invitations.');
    }

    $systemDuplicate = $pdo->prepare(
        'SELECT user_id FROM users WHERE email = ? LIMIT 1',
    );
    $systemDuplicate->execute([$email]);
    if ($systemDuplicate->fetchColumn()) {
        throw new RuntimeException('That email address already belongs to a system user.');
    }
    $customerDuplicate = $pdo->prepare(
        'SELECT customer_id FROM customers WHERE email = ? LIMIT 1',
    );
    $customerDuplicate->execute([$email]);
    if ($customerDuplicate->fetchColumn()) {
        throw new RuntimeException('That email address already belongs to a customer account.');
    }

    expire_registration_invitations($pdo);
    $expiresAt = (new DateTimeImmutable('now'))
        ->modify('+' . $expiresInHours . ' hours')
        ->format('Y-m-d H:i:s');
    $prefix = $role === 'Admin' ? 'AQ-AD' : 'AQ-ST';

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $token = $prefix . '-' . strtoupper(bin2hex(random_bytes(24)));
        try {
            $statement = $pdo->prepare(
                "INSERT INTO user_invitations
                    (token_hash, email, role, created_by, expires_at, status)
                 VALUES (?, ?, ?, ?, ?, 'Active')",
            );
            $statement->execute([
                invitation_token_hash($token),
                $email,
                $role,
                $createdBy,
                $expiresAt,
            ]);

            return [
                'token' => $token,
                'expires_at' => $expiresAt,
                'role' => $role,
                'email' => $email,
            ];
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
        }
    }

    throw new RuntimeException('Could not create a unique invitation code.');
}

/**
 * Locks and validates an invitation inside the caller's transaction.
 * Email and role are read only from the database; neither comes from the form.
 *
 * @return array{id:int,email:string,role:string}|null
 */
function claim_registration_invitation(
    PDO $pdo,
    string $expectedRole,
    string $token,
): ?array {
    if (!in_array($expectedRole, ['Admin', 'Staff'], true)) {
        return null;
    }

    $normalized = normalize_invitation_token($token);
    if ($normalized === '') {
        return null;
    }

    expire_registration_invitations($pdo);
    $statement = $pdo->prepare(
        "SELECT invitation_id, email, role, status, expires_at, used_at
         FROM user_invitations
         WHERE token_hash = ?
         LIMIT 1
         FOR UPDATE",
    );
    $statement->execute([hash('sha256', $normalized)]);
    $invitation = $statement->fetch();

    if (
        !$invitation ||
        $invitation['role'] !== $expectedRole ||
        $invitation['status'] !== 'Active' ||
        $invitation['used_at'] !== null ||
        strtotime((string) $invitation['expires_at']) <= time()
    ) {
        return null;
    }

    $email = strtolower((string) $invitation['email']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    return [
        'id' => (int) $invitation['invitation_id'],
        'email' => $email,
        'role' => (string) $invitation['role'],
    ];
}

function consume_registration_invitation(
    PDO $pdo,
    int $invitationId,
    int $newUserId,
): bool {
    $statement = $pdo->prepare(
        "UPDATE user_invitations
         SET status = 'Used', used_at = NOW(), used_by = ?
         WHERE invitation_id = ?
           AND status = 'Active'
           AND used_at IS NULL
           AND expires_at > NOW()",
    );
    $statement->execute([$newUserId, $invitationId]);

    return $statement->rowCount() === 1;
}

function revoke_registration_invitation(
    PDO $pdo,
    int $invitationId,
): bool {
    $statement = $pdo->prepare(
        "UPDATE user_invitations
         SET status = 'Revoked', revoked_at = NOW()
         WHERE invitation_id = ?
           AND status = 'Active'
           AND used_at IS NULL
           AND expires_at > NOW()",
    );
    $statement->execute([$invitationId]);
    return $statement->rowCount() === 1;
}

/** @param array{token:string,expires_at:string,role:string,email:string} $invitation */
function remember_created_invitation(array $invitation): void
{
    $_SESSION['new_registration_invitation'] = $invitation;
}

/** @return array{token:string,expires_at:string,role:string,email:string}|null */
function pull_created_invitation(): ?array
{
    $invitation = $_SESSION['new_registration_invitation'] ?? null;
    unset($_SESSION['new_registration_invitation']);

    return is_array($invitation) &&
        isset($invitation['token'], $invitation['expires_at'], $invitation['role'], $invitation['email'])
        ? $invitation
        : null;
}
