# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] — 2026-09-02

### Added

- **`TennisMatch::$has_analysis` / `$has_market`** (`?bool`) — whether a
  model thesis or profile, or a match-winner market, exists for the match.
  On every row of `/matches` and on the match detail, every tier (API 1.9.0,
  shipped 2026-09-02). Filter the slate on them before calling
  `/matches/{id}/analysis` or `/markets/{id}/prices`, which answer
  `404 no_analysis` / `404 no_market` about the same fact. Null only against
  an older server that does not send them.
- **`TennisMatch::$event_status_updated_at`** — the instant the current
  `event_status` was recorded (ISO 8601 UTC, API 2026-08-19; null when the
  status has never changed since the field was introduced — never
  backfilled). On `main` since 2026-08-19, first released here.

## [1.1.1] — 2026-08-16

### Fixed

- History packages: documented all four `kind` values — `tape` (default),
  `rankings` (ULTRA), `rally` (the charted rally corpus as yearly exports,
  ULTRA) and `archive` (the 1968–2022 results archive as yearly exports,
  same entitlement as the tape packages) — across the `listHistoryPackages()`
  / `getHistoryPackage()` docblocks, the `HistoryPackage` model and the
  README. The yearly kinds use a bare-year `YYYY` period, and their files
  carry a `compression: gzip` entry.

### Added

- Tests for the yearly `rally`/`archive` package kinds: bare-year periods,
  `kind` query pass-through and the gzip `compression` file entry.

### Changed

- README rebuilt with the fleet-standard centered header (org banner, badge
  row, links block), matching the JS/Python/MCP clients.
- Webhook test fixture secret made self-evidently fake (GitGuardian
  false-positive hygiene).

## [1.1.0] — 2026-08-07

Full parity with the public API surface (33 documented endpoints).

### Added

- **Head-to-head**: `getHeadToHead()` (`/h2h`, BASIC) — archive (1968–2022) +
  current (2023→now) meetings, `HeadToHead`/`H2HMeeting` models.
- **Results archive** (BASIC): `listArchiveMatches()`, `getArchiveMatch()`
  (with pre-/post-1991 serve stats semantics), `listArchivePlayers()`,
  `getArchiveCareer()` — `ArchiveMatch`, `ArchivePlayer`, `ArchivePlayerBio`,
  `ArchiveCareer` models.
- **Per-match tape**: `getHistoryMatch()` (`/history/matches/{id}`, BASIC)
  with `sequence=raw|clean` — `HistoryTape`/`HistoryTapeRow` models, including
  `point_winner` (clean rows only) and the per-set `tiebreaks` block.
- **Rally construction** (ULTRA): `listRallyMatches()`, `getRallyMatch()`,
  `getMatchRally()` (by our match id; distinct 404 `not_charted`) —
  `RallyMatch`, `RallyPoint`, `RallyShot` models.
- **Charting** (ULTRA): `getChartingPlayer()`, `getChartingMatch()`.
- **In-play statistics**: `getMatchStatistics()` (`/matches/{id}/statistics`,
  ULTRA) — derived + measured families with per-family freshness, typed as
  `MatchStatistics`/`MatchStatisticsSide`.
- **Rankings**: `listRankings()` (`/rankings`) — listing mode (PRO, full
  published table for one system) and per-player as-of mode (ULTRA), with
  `RankingRecord` (incl. `previous_rank`) and `RankingListMeta.coverage`.
- **Push feed**: `getWsToken()` (`/ws-token`, ULTRA).
- **Bulk packages** (PRO): `listHistoryPackages()` with `kind=tape|rankings`
  and the `year` archive listing (ULTRA-gated), `getHistoryPackage()`
  manifests — `HistoryPackage` model.
- **Tournaments** (FREE): `listTournaments()`, `getTournament()` — the
  catalogue `Match.tournament_id` joins.
- **Usage**: `getUsage()` (`/usage`, any tier, quota-exempt).
- **Bare match prices**: `listMatchPrices()` (`/matches/{id}/prices`, PRO)
  with `limit`/`minutes` windowing; `Price` gains `price_source` +
  `synthetic`.
- **Webhooks** (ULTRA, direct keys only): `createWebhook()` (secret shown
  once), `listWebhooks()`, `deleteWebhook()`; new `Conflict` (409) exception
  for `webhook_limit`. POST requests are never retried.
- **Match model fields**: `tour` (filter vocabulary), `tournament_id`,
  `round_code`, `withdrew`, plus the history-list `tape` coverage block.
- **List filters**: `$filters` on `listMatches()`/`listCompletedMatches()` —
  `player` (repeatable, max 50, client-enforced), `country`, `from`/`to`,
  `coverage` — and `juniors` in the documented `tour` vocabulary. Repeatable
  params are sent in explode form (`player=1&player=2`).
- **ListMeta**: `total` (nullable) and `has_more`.
- **Errors**: `AbuseThrottled` (429 `abuse_throttled`, 24-hour block) carrying
  `retry_at_epoch` — never auto-retried; daily-quota 429s surface
  `getResetsAt()` (absolute ISO instant), `getScope()` and
  `getLimitPerDay()` on `RateLimited`.
- `scripts/truthcheck.sh` — CI guard pinning quota/URL copy to product truth.

### Changed

- README rewritten to the fleet standard: quota table for the 2026-08-06 grid
  (FREE 100/day, BASIC 1,000/day, PRO 10,000/day, ULTRA 500,000/day),
  five-tour phrasing (ATP, WTA, Challenger, ITF and juniors), tier-gated
  endpoint table, free-tier polling guidance.
- `paginate()` accepts trailing args after limit/offset so filtered lists can
  be paginated.

## [1.0.0] — 2026-08-02

- Initial release: matches, scores, events, analysis, players, markets,
  history list, fixtures; PSR-18 transport with retries; typed error
  taxonomy; offline fixture test suite.
