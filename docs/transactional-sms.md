# Transactional shipment SMS

SUKI shipment SMS is an asynchronous, transactional notification channel. It does not authorize a rider, change a shipment state, or replace in-app notifications.

The safe default is non-network logging:

```env
TRANSACTIONAL_SMS_ENABLED=false
TRANSACTIONAL_SMS_DRIVER=log
TRANSACTIONAL_SMS_SENDER="SUKI SHOP"
```

For a reviewed Semaphore deployment, use the allowlisted `semaphore` driver and provide credentials through deployment secrets only:

```env
TRANSACTIONAL_SMS_ENABLED=true
TRANSACTIONAL_SMS_DRIVER=semaphore
TRANSACTIONAL_SMS_ENDPOINT=https://api.semaphore.co/api/v4/messages
TRANSACTIONAL_SMS_API_KEY=...
TRANSACTIONAL_SMS_TIMEOUT=4
```

Run the normal queue worker after the migration is reviewed and deployed:

```bash
php artisan queue:work --tries=3 --backoff=30,120,300
```

The system records one notification per shipment, event, and recipient. Queue retries update the same record. A successful terminal event is never automatically resent. Logistics may retry only failed, provider-owned shipment notifications; the retry is audited through the notification record. Phone numbers are normalized to `+63` mobile format and masked in interfaces and logs.

Before production enablement: review the migration, queue worker availability, provider account credits, sender registration, credentials in the deployment secret store, and a staging test. No real SMS can send until an enabled provider and valid provider credentials are configured.
