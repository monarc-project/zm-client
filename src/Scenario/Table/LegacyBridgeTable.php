<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Table;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Throwable;

/**
 * Owns the Scenario bridge persistence on the FrontOffice client database.
 *
 * This table is built through ClientEntityManagerFactory, so all handoffs and
 * sessions use orm_cli. Raw database access must stay in this class.
 */
final class LegacyBridgeTable
{
    private Connection $connection;

    public function __construct(EntityManager $entityManager)
    {
        $this->connection = $entityManager->getConnection();
    }

    public function isReady(): bool
    {
        try {
            $this->connection->executeQuery('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function createHandoff(
        string $code,
        string $nonce,
        int $userId,
        int $anrId,
        string $office,
        string $returnPath,
        string $expiresAt,
        string $legacyToken
    ): void {
        $this->connection->insert('scenario_legacy_handoffs', [
            'code_digest' => hash('sha256', $code),
            'nonce_digest' => hash('sha256', $nonce),
            'user_id' => $userId,
            'anr_id' => $anrId,
            'office' => $office,
            'audience' => 'scenario-ui',
            'return_path' => $returnPath,
            'expires_at' => $expiresAt,
            // The source token never leaves the authenticated legacy request.
            'legacy_token_digest' => hash('sha256', $legacyToken),
        ]);
    }

    /**
     * Atomically consumes a handoff and creates its Scenario session.
     *
     * @return array{userId: int, anrId: int, office: string, expiresAt: string}|null
     */
    public function consumeHandoff(
        string $code,
        string $nonce,
        string $audience,
        string $session,
        string $sessionExpiresAt,
        string $scenarioCurrentAt,
        string $legacyCurrentAt
    ): ?array {
        $transactionStarted = false;
        try {
            $this->connection->beginTransaction();
            $transactionStarted = true;
            $row = $this->connection->fetchAssociative(
                'SELECT * FROM scenario_legacy_handoffs WHERE code_digest = ? FOR UPDATE',
                [hash('sha256', $code)]
            );
            if (!$row
                || $row['consumed_at']
                || $row['expires_at'] <= $scenarioCurrentAt
                || $row['audience'] !== $audience
                || strlen($row['legacy_token_digest']) !== 64
                || !hash_equals($row['nonce_digest'], hash('sha256', $nonce))
            ) {
                $this->connection->rollBack();

                return null;
            }

            if ($this->connection->update(
                'scenario_legacy_handoffs',
                ['consumed_at' => gmdate('Y-m-d H:i:s')],
                ['id' => $row['id'], 'consumed_at' => null]
            ) !== 1) {
                $this->connection->rollBack();

                return null;
            }

            $legacyTokenId = $this->connection->fetchOne(
                'SELECT ut.id FROM users u '
                . 'INNER JOIN users_anrs ua ON ua.user_id = u.id AND ua.anr_id = ? '
                . 'INNER JOIN user_tokens ut ON ut.user_id = u.id '
                . 'WHERE u.id = ? AND u.status = 1 AND SHA2(ut.token, 256) = ? '
                . 'AND ut.date_end > ? LIMIT 1 FOR UPDATE',
                [$row['anr_id'], $row['user_id'], $row['legacy_token_digest'], $legacyCurrentAt]
            );
            if ($legacyTokenId === false) {
                $this->connection->rollBack();

                return null;
            }

            $this->connection->insert('scenario_sessions', [
                'session_digest' => hash('sha256', $session),
                'legacy_token_digest' => $row['legacy_token_digest'],
                'user_id' => $row['user_id'],
                'anr_id' => $row['anr_id'],
                'office' => $row['office'],
                // This is a hard Scenario-session lifetime. The source token
                // retains its normal sliding MONARC lifetime on resolve.
                'expires_at' => $sessionExpiresAt,
            ]);
            $this->connection->commit();
            $transactionStarted = false;

            return [
                'userId' => (int) $row['user_id'],
                'anrId' => (int) $row['anr_id'],
                'office' => $row['office'],
                'expiresAt' => $sessionExpiresAt,
            ];
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Re-authorises a Scenario session and renews its source MONARC token in
     * one transaction. The opaque Scenario session is never an identity
     * source: all current user, ANR and source-token checks happen here before
     * the normal sliding expiry is written.
     *
     * @return array{user_id: int, anr_id: int, office: string, expires_at: string}|null
     */
    public function refreshActiveSession(
        string $session,
        string $legacyTokenExpiresAt,
        string $scenarioCurrentAt,
        string $legacyCurrentAt
    ): ?array {
        $transactionStarted = false;
        try {
            $this->connection->beginTransaction();
            $transactionStarted = true;
            $row = $this->connection->fetchAssociative(
                'SELECT ss.user_id, ss.anr_id, ss.office, ss.expires_at, ut.id AS legacy_token_id '
                . 'FROM scenario_sessions ss '
                . 'INNER JOIN users u ON u.id = ss.user_id AND u.status = 1 '
                . 'INNER JOIN users_anrs ua ON ua.user_id = u.id AND ua.anr_id = ss.anr_id '
                . 'INNER JOIN user_tokens ut ON ut.user_id = u.id '
                . 'AND SHA2(ut.token, 256) = ss.legacy_token_digest '
                . 'WHERE ss.session_digest = ? AND ss.revoked_at IS NULL '
                . 'AND ss.expires_at > ? AND ut.date_end > ? '
                . 'LIMIT 1 FOR UPDATE',
                [hash('sha256', $session), $scenarioCurrentAt, $legacyCurrentAt]
            );
            if (!$row) {
                $this->connection->rollBack();

                return null;
            }

            $this->connection->update(
                'user_tokens',
                ['date_end' => $legacyTokenExpiresAt],
                ['id' => $row['legacy_token_id']]
            );

            $this->connection->commit();
            $transactionStarted = false;

            unset($row['legacy_token_id']);

            /** @var array{user_id: int, anr_id: int, office: string, expires_at: string} $row */
            return $row;
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }

    public function revokeSession(string $session): void
    {
        $this->connection->update(
            'scenario_sessions',
            ['revoked_at' => gmdate('Y-m-d H:i:s')],
            ['session_digest' => hash('sha256', $session)]
        );
    }

    public function revokeUserSessions(int $userId): void
    {
        $this->connection->executeStatement(
            'UPDATE scenario_sessions SET revoked_at = UTC_TIMESTAMP() WHERE user_id = ? AND revoked_at IS NULL',
            [$userId]
        );
    }
}
