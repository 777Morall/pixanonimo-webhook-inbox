<?php
declare(strict_types=1);
// https://pixanonimo.net — eventos artificiais e banco em memória.
require __DIR__ . '/../src/Signature.php';
require __DIR__ . '/../src/Inbox.php';
use PixAnonimo\Webhooks\Inbox;
use PixAnonimo\Webhooks\Signature;

$checks=0;
function check(bool $condition, string $message): void {
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    $checks++;
}
function rejects(callable $callback, string $expected): void {
    try { $callback(); } catch (Throwable $exception) {
        check($exception instanceof $expected, 'Exceção inesperada: '.get_class($exception)); return;
    }
    throw new RuntimeException('Uma entrada inválida foi aceita.');
}
$key=random_bytes(32);
$now=time();
$timestamp=(string)$now;
$verifier=new Signature($key);
$db=new PDO('sqlite::memory:');
$inbox=new Inbox($db,$verifier);
$body='{"id":"event_test_1","type":"payment.confirmed"}';
$signed=fn(string $payload, string $ts) => hash_hmac('sha256',$ts.'.'.$payload,$key);
$sig=$signed($body,$timestamp);
check($inbox->accept($body,$timestamp,$sig,$now)['duplicate'] === false,'Primeiro evento');
check($inbox->accept($body,$timestamp,$sig,$now)['duplicate'] === true,'Reenvio idempotente');
check((int)$db->query('SELECT COUNT(*) FROM webhook_events')->fetchColumn()===1,'Uma única gravação');
check(!$verifier->verify($body,$timestamp,str_repeat('0',64),$now),'Assinatura incorreta');
check(!$verifier->verify($body.' ',$timestamp,$sig,$now),'Alteração do corpo invalida assinatura');
check(!$verifier->verify($body,(string)($now-301),$signed($body,(string)($now-301)),$now),'Evento expirado');
check(!$verifier->verify($body,(string)($now+301),$signed($body,(string)($now+301)),$now),'Evento futuro');
check($verifier->verify($body,(string)($now-300),$signed($body,(string)($now-300)),$now),'Limite de tolerância');
check(!$verifier->verify($body,'1e9',$sig,$now),'Timestamp malformado');
rejects(fn()=>new Signature('short'),InvalidArgumentException::class);
rejects(fn()=>$inbox->accept($body,$timestamp,str_repeat('0',64),$now),UnexpectedValueException::class);
$different='{"id":"event_test_1","type":"payment.confirmed","different":true}';
rejects(fn()=>$inbox->accept($different,$timestamp,$signed($different,$timestamp),$now),DomainException::class);
foreach (['{','null','{"id":[],"type":"payment.confirmed"}','{"id":"x","type":"other"}','{"id":"../x","type":"payment.confirmed"}'] as $bad) {
    rejects(fn()=>$inbox->accept($bad,$timestamp,$signed($bad,$timestamp),$now),InvalidArgumentException::class);
}
$large=str_repeat('x',65_537);
rejects(fn()=>$inbox->accept($large,$timestamp,$signed($large,$timestamp),$now),LengthException::class);
check((int)$db->query('SELECT COUNT(*) FROM webhook_events')->fetchColumn()===1,'Entradas rejeitadas não geram registros');
check($db->query('PRAGMA table_info(webhook_events)')->fetchAll(PDO::FETCH_COLUMN,1) === ['event_id','payload_hash','event_type','received_at'],'Payload e segredo não são persistidos');
echo "OK: {$checks} verificações de webhooks.\n";
