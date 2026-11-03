# Saverr — Mobile API

Base URL: `http://kennedy.test/api/v1`

Every response uses the same envelope:

```json
{ "status": true, "message": "optional", "data": { } }
```

Errors:

```json
{ "status": false, "message": "The given data was invalid.", "errors": { "email": ["..."] } }
```

Paginated lists add a `meta` block: `current_page`, `last_page`, `per_page`, `total`, `has_more`.

Authenticated calls send `Authorization: Bearer <token>` and `Accept: application/json`.
Tokens are Sanctum personal access tokens and expire after 60 days
(`SANCTUM_TOKEN_EXPIRATION`, in minutes).

---

## Rate limits

| Limiter | Applies to | Allowance |
|---|---|---|
| `auth` | register, login | 5/min per email+IP, 20/min per IP |
| `password-reset` | sending a reset code | 3/hour per email, 10/hour per IP |
| `otp` | verify-otp, reset-password | 6/min per email+IP, 20/hour per email, 20/min per IP |
| `public` | unauthenticated content | 60/min per IP |
| `api` | all authenticated reads | 90/min per user |
| `writes` | create / update / delete | 30/min per user |
| `webhook` | RevenueCat webhook | 120/min per IP |

Login also has its own lockout: 5 failed attempts per email+IP locks that pair
for 15 minutes. Exceeding any limiter returns `429`.

---

## Status codes

| Code | Meaning |
|---|---|
| 200 / 201 | Success |
| 401 | Missing, expired or invalid token |
| 402 | Plan limit reached — show the Go Premium screen (`upgrade_required: true`) |
| 403 | Not your record, or the account is suspended |
| 404 | Not found |
| 422 | Validation failed (`errors` object) |
| 429 | Rate limited |

A `402` body carries `error_code: "plan_limit_reached"`, `feature`, `limit` and `used`.

---

## Public — no token

| Method | Endpoint | Screen |
|---|---|---|
| GET | `/app/config` | app bootstrap, support email, min version, maintenance flag |
| GET | `/app/plans` | Paywall |
| GET | `/app/bill-categories` | 11 Add Bill categories |

The onboarding slides (02.2–02.4) and the FAQ list on Help & Support (22) are
static copy shipped inside the app, so there is no endpoint for them. The
support address shown under "Get In Touch" comes from `/app/config`
(`support_email`) and is editable in the admin panel.

## Authentication

| Method | Endpoint | Body |
|---|---|---|
| POST | `/auth/register` | `name`, `email`, `password`, `password_confirmation`, `phone?`, `timezone?`, `device_name?` |
| POST | `/auth/login` | `email`, `password`, `device_name?` |
| POST | `/auth/forgot-password` | `email` — sends a 6-digit code |
| POST | `/auth/verify-otp` | `email`, `otp` — returns a `reset_token` |
| POST | `/auth/reset-password` | `email`, `reset_token`, `password`, `password_confirmation` |
| GET | `/auth/me` | — |
| POST | `/auth/logout` | revokes the current token |
| POST | `/auth/logout-all` | revokes every token |
| PUT | `/auth/password` | `current_password`, `password`, `password_confirmation` |

`register` and `login` return `{ user, token, token_type }`.

### Password reset by one-time code

Three steps — there is no emailed reset link.

```
POST /auth/forgot-password   { "email": "..." }
  → a 6-digit code is emailed, valid 10 minutes

POST /auth/verify-otp        { "email": "...", "otp": "123456" }
  → { "reset_token": "<64 chars>", "expires_in_minutes": 15 }

POST /auth/reset-password    { "email": "...", "reset_token": "...",
                               "password": "...", "password_confirmation": "..." }
  → password set, every device signed out, token burned
```

What protects it:

- Only hashes are stored (`password_reset_otps`), so a leaked row cannot be replayed.
- Codes come from `random_int`, not `rand`.
- One live code per address; asking again replaces the previous one.
- Five wrong guesses burn the code, on top of the endpoint throttles below.
- The `reset_token` is single-use and expires after 15 minutes; replaying it returns 422.
- Every failure returns one identical 422, so nothing leaks about which part was wrong.
- `forgot-password` returns the same message registered or not, so it cannot be used
  to discover accounts.

**While no mailer is configured**, `forgot-password` also returns the code in the
response so the flow can be tested without SMTP:

```json
{
  "status": true,
  "message": "If that email is registered, a reset code is on its way.",
  "data": { "expires_in_minutes": 10, "otp_length": 6, "otp": "572066",
            "otp_is_exposed_because": "No real mailer is configured yet. ..." }
}
```

This switches itself off — no code change needed. The field appears only when
`MAIL_MAILER` is `log` or `array` **and** the app is not in production. Set
`MAIL_MAILER=smtp` and the response drops back to `{ expires_in_minutes, otp_length }`.

The email itself is a queued Laravel notification, so a queue worker
(`php artisan queue:work`) must be running for real delivery.

## Onboarding — screens 05, 06, 07

| Method | Endpoint | Notes |
|---|---|---|
| GET | `/onboarding/quick-picks` | quick-pick bill chips |
| POST | `/onboarding` | every field optional, so the app can post step by step |
| POST | `/onboarding/complete` | marks setup finished |

```json
{
  "pay_frequency": "biweekly",
  "next_payday": "2026-11-13",
  "estimated_amount": 1600,
  "bills": [
    { "name": "Rent", "amount": 1200, "due_date": "2026-11-10",
      "recurrence": "monthly", "bill_category_id": 1 }
  ],
  "complete": true
}
```

Saving a pay schedule also creates or updates the user's primary income source,
so every later calculation reads from one place.

## Dashboard — screen 08

`GET /dashboard` returns:

- `greeting` — salutation (from the user's timezone), name, subtitle
- `set_aside` — `amount`, `next_paycheck`, `covers_until`, `smart_splits`
- `allowance` — `leftover`, `days_until_payday`, `daily`, `weekly`, `view`
- `upcoming_bills` — next three unpaid occurrences
- `overview` — `monthly_income`, `remaining_balance`, `monthly_bills`
- `unread_notifications`, `onboarding_completed`

## Profile, settings, account — screens 19, 20

| Method | Endpoint | Notes |
|---|---|---|
| GET | `/profile` | |
| POST | `/profile` | multipart when sending `avatar` |
| GET / PUT | `/settings` | `notifications_enabled`, `currency`, `allowance_view`, `timezone` |
| GET | `/account/export` | Export Data |
| DELETE | `/account` | requires `password` |

## Bills — screens 10, 11

| Method | Endpoint | Notes |
|---|---|---|
| GET | `/bills` | `search`, `category_id`, `status`, `sort_by`, `sort_dir`, `per_page` |
| GET | `/bills/summary` | `month=YYYY-MM` → total / paid / unpaid |
| POST | `/bills` | `name`, `amount`, `due_date`, `recurrence`, `bill_category_id?`, `color?`, `reminder_days_before?`, `notes?` |
| GET | `/bills/{bill}` | bill plus its schedule |
| PUT | `/bills/{bill}` | changing amount, date or recurrence rebuilds future occurrences |
| DELETE | `/bills/{bill}` | soft delete; paid history is kept |

`sort_by` is restricted to `next_due_date`, `due_date`, `amount`, `name`,
`created_at`; anything else is a 422.

### Dated occurrences

| Method | Endpoint |
|---|---|
| GET | `/bill-occurrences?from=&to=&status=` |
| POST | `/bill-occurrences/{occurrence}/pay` — `paid_amount?`, `paid_at?` |
| POST | `/bill-occurrences/{occurrence}/unpay` |
| POST | `/bill-occurrences/{occurrence}/skip` |

Paying a bill writes a matching expense transaction and sends a notification.

## Income — screen 09

| Method | Endpoint |
|---|---|
| GET / POST | `/income-sources` |
| GET / PUT / DELETE | `/income-sources/{incomeSource}` |
| GET / POST | `/paychecks` |

`POST /paychecks` records the money, writes an income transaction, rolls the
next payday forward and returns the freshly recalculated set-aside.

## Savings jars — screens 13, 15, Edit Jar

| Method | Endpoint |
|---|---|
| GET / POST | `/savings-jars` |
| GET / PUT / DELETE | `/savings-jars/{savingsJar}` |
| POST | `/savings-jars/{savingsJar}/funds` — `amount`, `note?`, `occurred_on?` |
| POST | `/savings-jars/{savingsJar}/withdraw` |

Crossing 25 / 50 / 75 / 100 % fires a milestone notification.

## Calendar — screen 12

`GET /calendar?month=YYYY-MM` returns one entry per day with three kinds of marker:

| `type` | `style` | Meaning |
|---|---|---|
| `bill_due` | solid | a bill due that day, in its category colour |
| `payday` | solid | expected paycheck |
| `set_aside` | dashed | what to hold back on the next payday, per bill |

## Insights, My Plan, transactions

| Method | Endpoint | Screen |
|---|---|---|
| GET | `/insights?days=30` | Insights — totals, income/expense split, category breakdown, history |
| GET | `/my-plan?month=YYYY-MM` | My Plan — income vs bills for the month |
| GET | `/transactions?type=&from=&to=` | transaction list |

Free accounts are capped at 30 days of history; the response says so with
`range.capped_by_plan`.

## Notifications — screen 18

| Method | Endpoint |
|---|---|
| GET | `/notifications?filter=all\|unread` |
| GET | `/notifications/unread-count` |
| POST | `/notifications/{id}/read` |
| POST | `/notifications/read-all` |
| DELETE | `/notifications/{id}` |

Served from Laravel's own `notifications` table. Each row carries `type`,
`title`, `body`, `payload`, `is_read` and `time_ago`.

The admin panel has no notification screen at all — nothing is composed, sent or
inspected there. Notifications are raised either by the app as things happen, or
by the daily cron:

| `type` | Raised by | Example from the design |
|---|---|---|
| `bill_paid` | app, when a payment is recorded | "Your $1,290 rent payment has been recorded." |
| `paycheck_received` | app, when a paycheck is logged | "You received $1,600. Set aside $560 for upcoming bills." |
| `savings_milestone` | app, when a jar passes 25/50/75/100% | "Your Emergency Fund reached 64% of your goal!" |
| `bill_due` | cron, when a bill enters its reminder window | "Your $100 T-Mobile bill is due on Mar 5." |
| `paycheck_received` | cron, on the morning of a payday | "Payday! You should receive $1,600. Set aside $560…" |
| `streak` | cron, after two weeks of on-time payments | "You've paid all bills on time for 2 weeks!" |
| `spending_insight` | cron, on a week-on-week shift of 10%+ | "You spent 18% less than last week. Great job!" |

## Push devices

| Method | Endpoint | Body |
|---|---|---|
| POST | `/devices` | `push_token`, `platform` (`ios\|android\|web`), `device_name?`, `app_version?` |
| DELETE | `/devices` | `push_token` |

Register on login and whenever the FCM token is refreshed; unregister on logout.

## Subscription — Paywall

| Method | Endpoint |
|---|---|
| GET | `/subscription` — current plan, all plans, usage counts |
| POST | `/subscription/sync` — `plan_slug`, `provider_subscription_id`, `expires_at?`, `is_trial?` |
| POST | `/subscription/cancel` |
| POST | `/webhooks/revenuecat` — server-to-server (not called by the app) |

`sync` updates the UI immediately after a purchase or restore; the RevenueCat
webhook remains the source of truth and is verified against
`REVENUECAT_WEBHOOK_SECRET` in constant time.

---

## Plan limits

Enforced in one place (`App\Services\PlanGate`) and editable in the admin panel.

| | Free | Pro |
|---|---|---|
| Bills | 3 | unlimited |
| Income sources | 1 | unlimited |
| Savings jars | 0 | unlimited |
| History | 30 days | unlimited |
| Smart splits | no | yes |
| Weekly / bi-weekly recurrence | no | yes |
| Custom reminders | no | yes |
| Calendar themes | no | yes |

## How the set-aside figure is calculated

`App\Services\SetAsideCalculator`.

- **Free** — every unpaid bill due before the paycheck *after* the next one,
  in full. That is what has to be covered out of the next paycheck.
- **Pro (smart splits)** — each unpaid bill within 120 days is divided by the
  number of paydays that land on or before its due date, so one large bill
  never lands on a single paycheck.

Allowance is `(expected paycheck − set aside)` spread over the days until the
next payday, exposed as both a daily and a weekly figure.

---

## The cron

There is exactly one scheduled command, `saverr:cron`, and it runs daily at
08:00. In order it:

| # | Task | What it does |
|---|---|---|
| 1 | `schedules` | extends the rolling window of bill occurrences, flags overdue ones |
| 2 | `reminders` | sends `bill_due` for bills entering their reminder window |
| 3 | `payday` | sends `paycheck_received` on the morning of a payday |
| 4 | `streaks` | sends `streak` after two weeks of on-time payments |
| 5 | `insights` | sends `spending_insight` on a week-on-week shift of 10%+ |
| 6 | `subscriptions` | expires subscriptions past their end date |
| 7 | `prune` | deletes notifications older than 15 days |

Every task is idempotent — running the command twice in a day sends nothing
twice — so it is safe to retry or run manually.

Server cron entry:

```
* * * * * cd /path/to/kennedy && php artisan schedule:run >> /dev/null 2>&1
```

Run the whole job by hand, or just one task:

```
php artisan saverr:cron
php artisan saverr:cron --only=prune
```

Retention is `NOTIFICATIONS_PRUNE_DAYS` (default 15).

## Enabling push (FCM)

In-app notifications work with no extra setup. To add push:

1. Put the Firebase service account JSON somewhere readable, e.g.
   `storage/app/firebase/service-account.json`.
2. Set in `.env`:

```
FCM_ENABLED=true
FCM_PROJECT_ID=your-firebase-project-id
FCM_CREDENTIALS=/absolute/path/to/service-account.json
```

3. Run a queue worker (`php artisan queue:work`) — pushes are queued so a slow
   Firebase call never holds up an API response.

No SDK or third-party package is used: `App\Services\FcmClient` signs the
service-account JWT with openssl and calls the FCM HTTP v1 API directly. Tokens
Firebase reports as unregistered are deleted automatically.
