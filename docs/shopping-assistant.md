# SUKI shopping assistant

The compact Ask SUKI widget is fallback-first. Product recommendations use `ProductCatalogService`, so the same approved, active, in-stock, locally usable-media rules apply as the public catalog. Order lookup is server-scoped to the authenticated Buyer. Guests can only receive public catalog and FAQ answers.

By default no AI provider is called. Optional wording assistance requires all of:

```env
SHOPPING_ASSISTANT_ENABLED=true
SHOPPING_ASSISTANT_PROVIDER=openai # or gemini
SHOPPING_ASSISTANT_MODEL=...
SHOPPING_ASSISTANT_API_KEY=...
SHOPPING_ASSISTANT_ENDPOINT=...
SHOPPING_ASSISTANT_TIMEOUT=3
```

The adapter receives only the already-grounded response and minimal product facts. It never receives identity, address, phone, payment, proof, full order, or raw conversation data. Provider failure, timeout, invalid output, or absent configuration always returns the deterministic answer.

Chat history is capped, scoped to an authenticated Buyer or random server session token, and can be cleared. Product support handoff requires an explicit Buyer confirmation and uses the existing human Messaging service; it never sends a message automatically.
