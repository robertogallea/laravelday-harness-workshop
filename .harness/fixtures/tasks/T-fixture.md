---
id: T-fixture
status: approved            # contract dei replay: non è il T-001 del partecipante
scope:
  paths:
    - app/Actions/Refunds/**
    - app/Http/Controllers/Admin/**
    - app/Policies/OrderPolicy.php
    - database/migrations/*_partial_refunds*.php
    - routes/web.php
    - tests/Feature/Refunds/**
must_not_change:
  - .env*
  - database/migrations/2025_*
  - app/Services/RefundService.php
  - app/Payments/**
evidence:
  - vendor/bin/pest --group=REFUND-INV-1
  - vendor/bin/pest
approval_required:
  - dipendenze composer nuove
---

# T-fixture — contract dei replay

Lo usano solo `php .harness/bin/replay` e i verify del guard, così l'esito dei payload
non dipende da come hai scritto il tuo T-001. Non ha effetto sull'agente: gli hook leggono
i contract da `.harness/tasks/`.
