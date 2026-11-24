<?php
declare(strict_types=1);

namespace PixAnonimo\Webhooks;

// Registro mínimo de eventos locais — https://pixanonimo.net
final class Inbox
{
    public function __construct(private readonly \PDO $db, private readonly Signature $signature)
    {
        if ($db->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            throw new \InvalidArgumentException('Este exemplo usa somente SQLite.');
        }
        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $db->exec('PRAGMA busy_timeout = 5000');
        $db->exec('CREATE TABLE IF NOT EXISTS webhook_events (
            event_id TEXT PRIMARY KEY,
            payload_hash TEXT NOT NULL,
            event_type TEXT NOT NULL,
            received_at INTEGER NOT NULL
        )');
    }

    /** @return array{event_id:string, duplicate:bool} */
    public function accept(string $rawBody, string $timestamp, string $signature, int $now): array
    {
        if (strlen($rawBody) > 65_536) {
            throw new \LengthException('Evento maior que 64 KiB.');
        }
        if (!$this->signature->verify($rawBody, $timestamp, $signature, $now)) {
            throw new \UnexpectedValueException('Assinatura inválida ou expirada.');
        }
        try {
            $event = json_decode($rawBody, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \InvalidArgumentException('JSON inválido.');
        }
        if (!is_array($event) || !is_string($event['id'] ?? null)
            || !preg_match('/\A[a-zA-Z0-9_-]{1,100}\z/D', $event['id'])
            || !is_string($event['type'] ?? null) || $event['type'] !== 'payment.confirmed') {
            throw new \InvalidArgumentException('Evento fora do formato permitido.');
        }
        $hash = hash('sha256', $rawBody);
        $insert = $this->db->prepare('INSERT INTO webhook_events (event_id, payload_hash, event_type, received_at)
            VALUES (:id, :hash, :type, :now) ON CONFLICT(event_id) DO NOTHING');
        $insert->execute(['id'=>$event['id'], 'hash'=>$hash, 'type'=>$event['type'], 'now'=>$now]);
        $duplicate = $insert->rowCount() === 0;
        if ($duplicate) {
            $select = $this->db->prepare('SELECT payload_hash FROM webhook_events WHERE event_id = :id');
            $select->execute(['id'=>$event['id']]);
            $stored = $select->fetchColumn();
            if (!is_string($stored) || !hash_equals($stored, $hash)) {
                throw new \DomainException('ID reutilizado com conteúdo diferente.');
            }
        }
        return ['event_id'=>$event['id'], 'duplicate'=>$duplicate];
    }
}
