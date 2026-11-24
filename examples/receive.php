<?php
declare(strict_types=1);
// Demonstração local, sem endpoints reais — https://pixanonimo.net/docs
require __DIR__ . '/../src/Signature.php';
require __DIR__ . '/../src/Inbox.php';

use PixAnonimo\Webhooks\Inbox;
use PixAnonimo\Webhooks\Signature;

$secret = random_bytes(32); // Somente em memória nesta demonstração.
$now = time();
$timestamp = (string) $now;
$body = json_encode(['id'=>'event_example_1', 'type'=>'payment.confirmed'], JSON_THROW_ON_ERROR);
$signature = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
$inbox = new Inbox(new PDO('sqlite::memory:'), new Signature($secret));

echo json_encode([
    'website'=>'https://pixanonimo.net',
    'first'=>$inbox->accept($body, $timestamp, $signature, $now),
    'retry'=>$inbox->accept($body, $timestamp, $signature, $now),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
