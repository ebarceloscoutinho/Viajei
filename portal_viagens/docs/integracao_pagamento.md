# Integração de pagamento automático (webhook)

O site já traz o **PIX copia e cola + QR Code** com valor exato. Para a confirmação deixar de ser manual, qualquer gateway/automação
(Mercado Pago, Stripe, Pagar.me, Make/Zapier, um script seu...) pode avisar o site quando o pagamento cair:

1. Defina um segredo longo em `inc/app.php`:  `const WEBHOOK_SEGREDO = '...';` (vazio = webhook desativado).
2. Faça um **POST JSON** para `https://SEU-SITE/api/webhook_pagamento.php`:

```json
{"reserva_id": 123, "valor": 189.90, "status": "pago", "referencia": "id-da-transacao-no-gateway"}
```

3. Cabeçalhos obrigatórios:
   - `X-Roteira-Timestamp`: segundos Unix atuais (tolerância de ±5 min);
   - `X-Roteira-Assinatura`: `hex( HMAC-SHA256( timestamp + "." + corpo_exato, WEBHOOK_SEGREDO ) )`.

O código identifica a reserva pelo **txid do PIX** (`RT000123` → reserva 123) — use isso para casar o pagamento recebido no gateway.

## Exemplo (curl)
```bash
CORPO='{"reserva_id":123,"valor":189.90,"status":"pago","referencia":"abc123"}'
TS=$(date +%s)
SIG=$(printf '%s.%s' "$TS" "$CORPO" | openssl dgst -sha256 -hmac "SEU_SEGREDO" -hex | sed 's/^.* //')
curl -X POST https://SEU-SITE/api/webhook_pagamento.php -H "Content-Type: application/json" \
  -H "X-Roteira-Timestamp: $TS" -H "X-Roteira-Assinatura: $SIG" -d "$CORPO"
```

## Comportamento
- Idempotente: avisar duas vezes não duplica nada (`ja_confirmada`).
- Só confirma reservas `aguardando_pagamento` ou `em_analise` e com valor **igual ou maior** ao devido.
- Gera o pagamento (`gateway:<referencia>`), confirma a reserva, lança a venda e o repasse previsto do guia no financeiro e envia e-mail/notificação ao cliente.
- Reserva já expirada/cancelada responde 409: trate manualmente (reembolso ou reativação).

## Importante
Cada gateway tem **seu próprio formato de notificação e de assinatura**. Este endpoint é o ponto de integração: coloque entre o gateway e ele
um pequeno adaptador (ou use Make/Zapier) que converta a notificação do gateway para o JSON acima e assine. A integração com um gateway
específico exige conta, credenciais e testes no ambiente do provedor — algo que depende de você.
