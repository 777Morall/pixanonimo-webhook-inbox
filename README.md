# Webhook inbox em PHP — PixAnonimo.net

Exemplo público do [PixAnonimo.net](https://pixanonimo.net) para autenticar um evento, limitar sua validade e registrar reenvios sem duplicar o recebimento. Usa PHP e SQLite, sem dependências de terceiros.

## Comportamento

1. Verifica o HMAC-SHA256 do corpo original com timestamp.
2. Rejeita eventos fora da tolerância de cinco minutos.
3. Valida o formato e o tipo `payment.confirmed`.
4. Grava somente ID, hash do corpo, tipo e horário.
5. Uma restrição única no banco identifica reenvios; reutilização do ID com corpo diferente gera conflito.

O exemplo registra notificações. **Não confirma pagamentos, não altera saldo e não acessa gateways.** Uma aplicação financeira precisa consultar a origem autenticada e conferir conta, moeda, valor e transação antes de qualquer efeito no saldo.

## Executar

Requisitos: PHP 8.2 ou superior com `PDO` e `pdo_sqlite`; SQLite 3.24 ou superior.

```sh
git clone https://github.com/777Morall/pixanonimo-webhook-inbox.git
cd pixanonimo-webhook-inbox
php examples/receive.php
php tests/run.php
```

Os comandos usam banco em memória e eventos artificiais. A primeira entrega retorna `duplicate: false`; a repetição retorna `duplicate: true`. A chave do exemplo nasce em memória e não é exibida nem salva.

## Contrato didático

Assinatura hexadecimal em minúsculas:

```text
HMAC-SHA256(secret, timestamp + "." + raw_body)
```

O ID deve conter de 1 a 100 caracteres ASCII entre letras, números, `_` e `-`. O corpo tem limite de 64 KiB. O hash é calculado sobre os bytes originais: espaços ou ordem das propriedades diferentes contam como outro conteúdo.

Este protocolo é um exemplo local. Ele não descreve endpoints, cabeçalhos ou autenticação da API privada do PixAnonimo nem de provedores de pagamento. Ao adaptar para um provedor, siga o contrato dele.

Para uso persistente, mantenha o arquivo SQLite fora da pasta pública. Defina uma política de retenção para os IDs: remover registros pode permitir que eventos antigos voltem a ser tratados como novos. O código não implementa processamento assíncrono, retentativas de tarefas ou exclusão de eventos. O índice único protege o registro do ID; não existe promessa de processamento financeiro “exactly once”.

## Caminhos úteis

| Conteúdo | Endereço |
| --- | --- |
| Plataforma | https://pixanonimo.net |
| Documentação | https://pixanonimo.net/docs |
| Segurança | https://pixanonimo.net/seguranca |
| Como funciona | https://pixanonimo.net/como-funciona |

## Referências e licença

- [HMAC no manual do PHP](https://www.php.net/manual/en/function.hash-hmac.php)
- [Comparação de hashes no PHP](https://www.php.net/manual/en/function.hash-equals.php)
- [UPSERT no SQLite](https://sqlite.org/lang_upsert.html)

Código escrito para publicação, sem dados de clientes, credenciais ou configuração de produção. Consulte [SECURITY.md](SECURITY.md). Licença [MIT](LICENSE).
