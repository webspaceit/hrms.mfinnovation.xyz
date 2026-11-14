<?php
// ============================================================
// PushSubscription - browser push endpoints saved by tenants.
//
// One tenant can have several devices/browsers, so the table is a
// list, not a 1:1 relation. Rows are removed when a push service
// answers 404/410 (subscription gone) or when the tenant turns
// notifications off.
// ============================================================

class PushSubscription extends BaseModel {

    protected $table = 'push_subscriptions';

    /** All subscriptions of one tenant. */
    public function forTenant($tenantId) {
        return $this->db->fetchAll(
            "SELECT * FROM push_subscriptions WHERE tenant_id = :tid ORDER BY id DESC",
            ['tid' => (int)$tenantId]
        );
    }

    public function countForTenant($tenantId) {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM push_subscriptions WHERE tenant_id = :tid",
            ['tid' => (int)$tenantId]
        );
    }

    public function byEndpoint($endpoint) {
        return $this->db->fetch(
            "SELECT * FROM push_subscriptions WHERE endpoint = :e",
            ['e' => (string)$endpoint]
        );
    }

    /**
     * Insert or refresh a subscription. Endpoints rotate, so the unique key is
     * the endpoint itself - re-subscribing on the same browser updates the row
     * (and re-binds it to whoever is logged in now).
     */
    public function register($tenantId, $endpoint, $p256dh, $auth, $userAgent = null) {
        $existing = $this->byEndpoint($endpoint);
        $data = [
            'tenant_id'  => (int)$tenantId,
            'p256dh'     => (string)$p256dh,
            'auth'       => (string)$auth,
            'user_agent' => mb_substr((string)$userAgent, 0, 255),
        ];
        if ($existing) {
            $this->update((int)$existing['id'], $data);
            return (int)$existing['id'];
        }
        $data['endpoint'] = (string)$endpoint;
        $data['last_error'] = null;
        return (int)$this->create($data);
    }

    /**
     * Remove a subscription. When $tenantId is given the delete is scoped to
     * that tenant, so one tenant can never unsubscribe another tenant's device.
     */
    public function removeByEndpoint($endpoint, $tenantId = null) {
        $sql = "DELETE FROM push_subscriptions WHERE endpoint = :e";
        $params = ['e' => (string)$endpoint];
        if ($tenantId !== null) {
            $sql .= " AND tenant_id = :tid";
            $params['tid'] = (int)$tenantId;
        }
        return $this->db->execute($sql, $params);
    }

    /** Record the outcome of the last delivery attempt. */
    public function markResult($id, $result) {
        if ($result === 'sent') {
            return $this->db->execute(
                "UPDATE push_subscriptions SET last_success_at = NOW(), last_error = NULL WHERE id = :id",
                ['id' => (int)$id]
            );
        }
        return $this->db->execute(
            "UPDATE push_subscriptions SET last_error = :err WHERE id = :id",
            ['id' => (int)$id, 'err' => mb_substr((string)$result, 0, 255)]
        );
    }
}
