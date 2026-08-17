#!/usr/bin/env bash
# REQ-TWIG-004 — fail when Twig applies but twig/extra-bundle (and demos) are not ready.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

has_twig=0
twig_roots=()
for dir in src templates Resources; do
  [[ -d "$dir" ]] && twig_roots+=("$dir")
done
if [[ ${#twig_roots[@]} -gt 0 ]] && find "${twig_roots[@]}" -type f -name '*.twig' | grep -q .; then
  has_twig=1
fi

if [[ "$has_twig" -eq 0 ]]; then
  echo "check-twig-extra: Twig does not apply (no templates) — OK"
  exit 0
fi

fail=0

if ! grep -q '"twig/extra-bundle"' composer.json; then
  echo "ERROR: twig/extra-bundle missing from package composer.json require (REQ-TWIG-004)" >&2
  fail=1
fi

if ! grep -q '"twig/string-extra"' composer.json; then
  echo "ERROR: twig/string-extra missing from package composer.json require (REQ-TWIG-004 baseline)" >&2
  fail=1
fi

shopt -s nullglob
for demo_composer in demo/*/composer.json demo/*/*/composer.json; do
  [[ -f "$demo_composer" ]] || continue
  demo_dir="$(dirname "$demo_composer")"
  bundles_php="$demo_dir/config/bundles.php"
  if [[ ! -f "$bundles_php" ]]; then
    continue
  fi
  if ! grep -q 'TwigBundle' "$bundles_php" 2>/dev/null; then
    continue
  fi
  if ! grep -q '"twig/extra-bundle"' "$demo_composer"; then
    echo "ERROR: $demo_composer missing twig/extra-bundle (REQ-TWIG-004)" >&2
    fail=1
  fi
  if ! grep -q 'Twig\\\\Extra\\\\TwigExtraBundle\\\\TwigExtraBundle' "$bundles_php" \
    && ! grep -q 'Twig\\Extra\\TwigExtraBundle\\TwigExtraBundle' "$bundles_php"; then
    echo "ERROR: $bundles_php missing TwigExtraBundle (REQ-TWIG-004)" >&2
    fail=1
  fi
done

if [[ "$fail" -ne 0 ]]; then
  exit 1
fi

echo "check-twig-extra: OK"
