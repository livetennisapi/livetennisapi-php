#!/bin/sh
# Truth-pin: keep quota numbers and canonical URLs in this repo consistent
# with the product. POSIX sh, no dependencies. Run from the repo root.
set -u

fail=0

# Tracked text files, excluding the CHANGELOG (its entries describe history)
# and this script (its patterns would match themselves).
files=$(git ls-files '*.md' '*.php' '*.json' '*.yml' | grep -v '^CHANGELOG\.md$')

say() { echo "truthcheck: $1"; fail=1; }

# --- forbidden ---------------------------------------------------------------

# Stale quota copy: a 100k/day figure died with the 2026-08-06 grid.
if echo "$files" | xargs grep -riE '(100,?000|100k)[^%]{0,40}(/day|per day|daily|requests a day)' -- 2>/dev/null | grep -q .; then
    say "stale 100k-style daily quota found:"
    echo "$files" | xargs grep -rinE '(100,?000|100k)[^%]{0,40}(/day|per day|daily|requests a day)' -- 2>/dev/null
fi

# The FREE tier is 100/day, never 1k/day.
if echo "$files" | xargs grep -riE 'free[^.]{0,60}(1,?000|1k) ?(requests)? ?(/day|per day|a day)' -- 2>/dev/null | grep -q .; then
    say "FREE tier paired with a 1,000/day quota:"
    echo "$files" | xargs grep -rinE 'free[^.]{0,60}(1,?000|1k) ?(requests)? ?(/day|per day|a day)' -- 2>/dev/null
fi

# Docs live at docs.livetennisapi.com, never livetennisapi.com/docs.
if echo "$files" | xargs grep -riE 'livetennisapi\.com/docs' -- 2>/dev/null | grep -q .; then
    say "wrong docs URL (use docs.livetennisapi.com):"
    echo "$files" | xargs grep -rinE 'livetennisapi\.com/docs' -- 2>/dev/null
fi

# Org identity only — no personal handle in this repo.
if echo "$files" | xargs grep -ri 'bensynapse' -- 2>/dev/null | grep -q .; then
    say "personal handle found (use the org identity):"
    echo "$files" | xargs grep -rin 'bensynapse' -- 2>/dev/null
fi

# The daily reset is an absolute local-midnight-derived instant, not midnight UTC.
if echo "$files" | xargs grep -ri 'midnight UTC' -- 2>/dev/null | grep -q .; then
    say "'midnight UTC' found (the daily reset is the resets_at instant):"
    echo "$files" | xargs grep -rin 'midnight UTC' -- 2>/dev/null
fi

# --- required ----------------------------------------------------------------

# If the repo states quotas, the FREE figure must be 100/day and the canonical
# docs host must be present.
if grep -qiE 'requests/(min|day)|per.day|quota' README.md 2>/dev/null; then
    if ! grep -qE '100( requests)?/day' README.md; then
        say "README states quotas but lacks the FREE '100/day' figure"
    fi
    if ! grep -q 'docs\.livetennisapi\.com' README.md; then
        say "README lacks docs.livetennisapi.com"
    fi
fi

if [ "$fail" -ne 0 ]; then
    echo "truthcheck: FAILED"
    exit 1
fi

echo "truthcheck: OK"
