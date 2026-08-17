<?php

declare(strict_types=1);

namespace App\Services;

use Laminas\Db\Adapter\Adapter;
use RuntimeException;

/**
 * Lab client tokens for /api/v2.
 *
 * Deliberately minimal. There are hundreds of LIS instances per country, so
 * handing out tokens by hand is not an option: a deployment ships one shared
 * enrollment key in its vlsm installer config, a LIS presents that key once,
 * and gets back a token it uses as a Bearer credential from then on.
 *
 * Only the sha256 of the token is stored. Re-enrolling an instance reissues its
 * token — that is how a LIS recovers after losing its copy, and how an admin
 * rotates one (revoke the row, let the client re-enroll). The consequence is
 * that anyone holding the deployment's enrollment key can rotate any client's
 * token in that deployment; the key is a per-country shared secret, so the
 * trust boundary is the same one that lets a LIS enroll in the first place.
 */
final class EnrollmentService
{
    public function __construct(
        private readonly LaminasBridge $bridge,
        private readonly ?string $enrollmentKey
    ) {
    }

    public function enrollmentEnabled(): bool
    {
        return $this->enrollmentKey !== null && $this->enrollmentKey !== '';
    }

    public function keyMatches(string $candidate): bool
    {
        return $this->enrollmentEnabled() && hash_equals((string) $this->enrollmentKey, $candidate);
    }

    /**
     * Issue (or reissue) a token for an instance.
     *
     * @return array{token: string, client_id: int, instance_uuid: string}
     */
    public function enroll(string $instanceUuid, ?int $labId, ?string $facilityCode, ?string $label, ?string $ip): array
    {
        $token = bin2hex(random_bytes(32));
        $hash = self::hash($token);
        $now = date('Y-m-d H:i:s');

        $this->adapter()->query(
            "INSERT INTO dash_api_clients
                (instance_uuid, lab_id, facility_code, label, token_hash, status, enrolled_on, enrolled_ip)
             VALUES (?, ?, ?, ?, ?, 'active', ?, ?)
             ON DUPLICATE KEY UPDATE
                lab_id = VALUES(lab_id),
                facility_code = VALUES(facility_code),
                label = VALUES(label),
                token_hash = VALUES(token_hash),
                status = 'active',
                enrolled_on = VALUES(enrolled_on),
                enrolled_ip = VALUES(enrolled_ip)",
            [$instanceUuid, $labId, $facilityCode, $label, $hash, $now, $ip]
        );

        $client = $this->findByInstance($instanceUuid);
        if ($client === null) {
            throw new RuntimeException('Enrollment row could not be read back after insert');
        }

        return [
            'token' => $token,
            'client_id' => (int) $client['client_id'],
            'instance_uuid' => $instanceUuid,
        ];
    }

    /**
     * Resolve a Bearer token to an active lab client.
     *
     * @return array<string, mixed>|null
     */
    public function authenticate(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $row = $this->adapter()->query(
            "SELECT client_id, instance_uuid, lab_id, facility_code, label, status
             FROM dash_api_clients
             WHERE token_hash = ? AND status = 'active'
             LIMIT 1",
            [self::hash($token)]
        )->current();

        return $row ? (array) $row : null;
    }

    /** Heartbeat, so `bin/console api-usage` can show who is still calling. */
    public function touch(int $clientId): void
    {
        $this->adapter()->query(
            'UPDATE dash_api_clients SET last_seen = ? WHERE client_id = ?',
            [date('Y-m-d H:i:s'), $clientId]
        );
    }

    public function revoke(int $clientId): bool
    {
        $result = $this->adapter()->query(
            "UPDATE dash_api_clients SET status = 'revoked' WHERE client_id = ? AND status = 'active'",
            [$clientId]
        );

        return $result->getAffectedRows() > 0;
    }

    /** @return array<int, array<string, mixed>> */
    public function listClients(): array
    {
        return $this->adapter()->query(
            'SELECT client_id, instance_uuid, lab_id, facility_code, label, status, enrolled_on, last_seen
             FROM dash_api_clients
             ORDER BY last_seen IS NULL, last_seen DESC, client_id ASC',
            Adapter::QUERY_MODE_EXECUTE
        )->toArray();
    }

    /** @return array<string, mixed>|null */
    public function findByInstance(string $instanceUuid): ?array
    {
        $row = $this->adapter()->query(
            'SELECT client_id, instance_uuid, lab_id, facility_code, label, status
             FROM dash_api_clients WHERE instance_uuid = ? LIMIT 1',
            [$instanceUuid]
        )->current();

        return $row ? (array) $row : null;
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function adapter(): Adapter
    {
        return $this->bridge->adapter();
    }
}
