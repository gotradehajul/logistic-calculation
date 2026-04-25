# Q3 — TOP Calculation: Additional Answers

## a) Why cap POD and ePOD delays individually rather than capping the total penalty?

Capping each delay type at 30 days individually enforces a **per-document-type
fairness rule**: neither a very late physical POD nor a very late ePOD can
impose more than 30 days of penalty on its own. This matters when one document
is submitted on time and the other is extremely late.

**Counter-example if we capped only the total penalty at 30:**
- POD late by 25 days, ePOD late by 25 days → total = 50 → capped at 30.
- POD late by 0 days, ePOD late by 45 days → total = 45 → capped at 30.

In the second scenario, ePOD alone would contribute the full 30-day cap, even
though the rule intends that neither document type can exceed 30 days
individually. By applying individual caps first, the maximum combined penalty is
always 60 days (before the final 45-day cap on the total TOP), and each
document's contribution is independently bounded.

In short: individual caps express a **business rule per document type**; a
total-only cap would allow one extremely late document to "use up" the entire
penalty budget, masking the other document's lateness status.

---

## b) What would happen without the final 45-day cap?

Without the 45-day cap, the maximum possible TOP result would be:
- Baseline TOP (uncapped, could be any value) + POD delay (max 30) + ePOD delay (max 30)
- Example: baseline = 30, both delays at max → 30 + 30 + 30 = **90 days**

**Problematic scenario:**
A transporter with a baseline TOP of 30 days submits both physical and digital
PODs 45 days late each. Without the cap:
- podDelay = 30, epodDelay = 30, total = 90 days

The transporter would wait **three months** to be paid — likely causing cash-flow
crises that make it impossible to cover fuel, driver salaries, and vehicle
maintenance. At that point the transporter may refuse further orders or go
bankrupt, disrupting the supply chain. The 45-day cap is a **business viability
safeguard** that balances accountability (lateness has a cost) with practicality
(payments must eventually happen).

---

## c) Making caps configurable

### Option 1 — Application config file (`config/top.php`)

```php
return [
    'max_delay_pod'  => (int) env('TOP_MAX_DELAY_POD',  30),
    'max_delay_epod' => (int) env('TOP_MAX_DELAY_EPOD', 30),
    'max_top'        => (int) env('TOP_MAX_TOP',        45),
];
```

The `TopCalculator` service reads from config instead of class constants:

```php
public function calculate(int $baselineTop, int $podLateDays, int $epodLateDays): int
{
    $maxPod  = config('top.max_delay_pod');
    $maxEpod = config('top.max_delay_epod');
    $maxTop  = config('top.max_top');

    $podDelay  = min(max($podLateDays,  0), $maxPod);
    $epodDelay = min(max($epodLateDays, 0), $maxEpod);

    return min($baselineTop + $podDelay + $epodDelay, $maxTop);
}
```

Values are set via `.env` and can be changed without a deploy (requires a
config cache clear: `php artisan config:clear`).

**Best for:** caps that rarely change and are environment-level settings
(e.g., different values for staging vs production).

---

### Option 2 — Database table (`top_config`)

```sql
CREATE TABLE top_config (
    key   VARCHAR(60) PRIMARY KEY,
    value INT NOT NULL,
    updated_at DATETIME
);

INSERT INTO top_config VALUES
    ('max_delay_pod',  30, NOW()),
    ('max_delay_epod', 30, NOW()),
    ('max_top',        45, NOW());
```

The service loads the config on boot (or per-request with cache):

```php
// In a TopConfigRepository
public function get(string $key, int $default): int
{
    return (int) Cache::remember("top_config:{$key}", 300, fn() =>
        TopConfig::where('key', $key)->value('value') ?? $default
    );
}
```

**Best for:** caps that need to change at runtime (e.g., adjusted by ops/finance
via a back-office UI) without redeploying or touching `.env`.

**Trade-off:** adds a DB read per calculation request (mitigated by caching),
and cache invalidation must be triggered after an update.

---

### Recommended approach

Use the **config file + env variable** approach (Option 1) as the default.
If a back-office admin UI requirement emerges later, promote to the database
approach (Option 2) and keep the config file as the fallback default.
