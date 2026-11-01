#!/usr/bin/env bash
# Dimostrazione end-to-end della catena dei checkpoint, su un remote "pubblico" locale.
#
#   bash .harness/tests/e2e-checkpoints.sh [<cartella di lavoro>]
#
# Docente: release cp-00 → partecipanti: clone, doctor --fix, inizia E1, E1 →
# docente: release cp-01 → inizia E2 (alice), prendi E1 (bob), salta cp-01 (carol),
# inizia E2 dalla chiavetta (dave). Richiede i tag cp-00 e cp-01 nel repo di build.
set -euo pipefail

BUILD="$(git -C "$(dirname "$0")" rev-parse --show-toplevel)"
WORK="${1:-$(mktemp -d)}"
rm -rf "$WORK" && mkdir -p "$WORK"
LOG="$WORK/output.log"
: > "$LOG"

pass=0
ok()   { echo "  ok    $*"; pass=$((pass + 1)); }
die()  { echo "  FAIL  $*"; echo "  (output completo in $LOG)"; exit 1; }
run()  { echo "\$ $*" >> "$LOG"; "$@" >> "$LOG" 2>&1; }          # deve riuscire
runx() { echo "\$ $*" >> "$LOG"; set +e; "$@" >> "$LOG" 2>&1; local e=$?; set -e; return $e; }
out()  { "$@" 2>&1 | tee -a "$LOG"; }

participant() {
  local who="$1"
  git clone -q "$WORK/public.git" "$WORK/$who"
  git -C "$WORK/$who" config user.name "$who"
  git -C "$WORK/$who" config user.email "$who@example.invalid"
  cp -a "$BUILD/vendor" "$WORK/$who/vendor"
  (cd "$WORK/$who" && run php .harness/bin/doctor --fix) || die "$who: doctor --fix"
  (cd "$WORK/$who" && run git switch -c mio-lavoro cp-00)
}

write_facts() { # $1 = cartella, $2 = numero di fatti oltre all'esempio
  local f="$1/.harness/memory/facts.md"
  git -C "$1" show cp-00:.harness/memory/facts.md > "$f"
  local rows=(
    '| Il rimborso lo autorizza `isStaff()`. | `git grep -n "isStaff()" -- app/Services` | oggi |'
    '| `refunds.amount` è in euro. | `php artisan model:show Refund` | oggi |'
    '| Il fake vale solo in Payments. | `grep -n Payments tests/Pest.php` | oggi |'
    '| Idempotenza solo su api/orders. | `php artisan route:list -v --path=api/orders` | oggi |'
    '| Un rimborso per ordine. | `php artisan db:table refunds` | oggi |'
  )
  for ((i = 0; i < $2; i++)); do echo "${rows[$i]}" >> "$f"; done
}

echo "Cartella di lavoro: $WORK"

echo "Docente: build e release cp-00"
for t in cp-00 cp-01; do git -C "$BUILD" rev-parse -q --verify "refs/tags/$t" > /dev/null || die "manca il tag $t nel repo di build (php workshop/bin/tag-checkpoints)"; done
git init -q --bare "$WORK/public.git"
git clone -q "$BUILD" "$WORK/build"
git -C "$WORK/build" fetch -q origin 'refs/tags/*:refs/tags/*'
git -C "$WORK/build" remote add public "$WORK/public.git"
(cd "$WORK/build" && run php workshop/bin/release cp-00 --remote=public) || die "release cp-00"
[[ "$(git -C "$WORK/public.git" tag)" == "cp-00" ]] && ok "il remote pubblico ha solo cp-00" || die "tag pubblici: $(git -C "$WORK/public.git" tag)"
[[ "$(git -C "$WORK/public.git" rev-parse main)" == "$(git -C "$WORK/build" rev-parse 'cp-00^{commit}')" ]] && ok "main pubblico = cp-00" || die "main pubblico"
(cd "$WORK/build" && out php workshop/bin/release cp-00 --remote=public) | grep -q "già pubblicato" && ok "release ripetuta: già pubblicato" || die "release ripetuta"

echo "Prework e mattina: alice"
participant alice
A="$WORK/alice"
grep -q "sk_live_WORKSHOP_CANARY_" "$A/.env" && ok "doctor --fix: .env con il canary" || die "canary"
(cd "$A" && runx php .harness/bin/verify cp-00) && ok "verify cp-00 verde sul clone" || die "verify cp-00"
(cd "$A" && runx php workshop/bin/checkpoint inizia E1) || die "inizia E1"
(cd "$A" && runx php .harness/bin/verify E1) && die "verify E1 verde prima di E1" || ok "verify E1 rosso prima di E1"
write_facts "$A" 5
(cd "$A" && runx php .harness/bin/verify E1) && ok "E1 fatta: verify E1 verde" || die "verify E1 dopo E1"
(cd "$A" && runx php workshop/bin/checkpoint prendi E1) && die "prendi E1 prima della release" || ok "prendi E1 rifiutato: cp-01 non ancora pubblicato"

echo "Docente: release cp-01"
(cd "$WORK/build" && run php workshop/bin/release cp-01 --remote=public) || die "release cp-01"
[[ "$(git -C "$WORK/public.git" tag | tr '\n' ' ')" == "cp-00 cp-01 " ]] && ok "il remote pubblico ha cp-00 e cp-01" || die "tag pubblici"

echo "alice: inizia E2 (percorso normale)"
mine="$(cat "$A/.harness/memory/facts.md")"
(cd "$A" && out php workshop/bin/checkpoint inizia E2) > "$WORK/alice-inizia.txt" || die "inizia E2"
grep -q '^\$ git fetch origin --tags --force' "$WORK/alice-inizia.txt" && ok "stampa il fetch" || die "fetch non stampato"
grep -q '^\$ git commit -m .E1: lavoro mio' "$WORK/alice-inizia.txt" && ok "commit del lavoro di E1" || die "commit E1"
grep -q '^\$ git checkout cp-01 -- .harness/checkpoints/cp-02.php' "$WORK/alice-inizia.txt" && ok "porta l'ingresso di E2 da cp-01" || die "ingresso E2"
grep -q 'E1: verde' "$WORK/alice-inizia.txt" && ok "verify E1 sul lavoro appena chiuso" || die "verify E1 in inizia"
[[ "$(cat "$A/.harness/memory/facts.md")" == "$mine" ]] && ok "facts.md di alice intatto" || die "facts.md sovrascritto"
[[ "$(git -C "$A" branch --show-current)" == "mio-lavoro" ]] && ok "alice resta su mio-lavoro" || die "branch"
[[ -z "$(git -C "$A" status --porcelain)" ]] && ok "working tree pulito" || die "working tree sporco"
(cd "$A" && runx php .harness/bin/verify E2) && die "verify E2 verde" || ok "verify E2 rosso (E2 da fare)"
(cd "$A" && runx php .harness/bin/verify cp-01) && ok "verify cp-01 verde per alice" || die "verify cp-01 alice"

echo "bob: indietro su E1 → prendi"
participant bob
B="$WORK/bob"
write_facts "$B" 2
(cd "$B" && out php workshop/bin/checkpoint prendi E1) > "$WORK/bob-prendi.txt" || die "prendi E1"
grep -q 'wip prima di prendere E1' "$WORK/bob-prendi.txt" && ok "commit wip prima di prendere" || die "wip"
grep -q 'Sostituisco con la soluzione di cp-01: .harness/memory/facts.md' "$WORK/bob-prendi.txt" && ok "elenca i file sovrascritti" || die "sovrascritti"
grep -q 'Sei allineato a cp-01' "$WORK/bob-prendi.txt" && ok "verify cp-01 verde dopo prendi" || die "verify dopo prendi"
git -C "$B" show HEAD~1:.harness/memory/facts.md | grep -q 'Il rimborso lo autorizza' && ok "la versione di bob resta nel commit wip" || die "wip perso"
[[ "$(git -C "$B" branch --show-current)" == "mio-lavoro" ]] && ok "bob resta su mio-lavoro" || die "branch bob"

echo "carol: stato confuso → salta"
participant carol
C="$WORK/carol"
echo "appunti a metà" > "$C/appunti.txt"
echo "// rotto" >> "$C/AGENTS.md"
mkdir -p "$C/.harness/state" && echo '{}' > "$C/.harness/state/sess.json" && echo '{}' > "$C/.harness/state/heartbeat.json"
(cd "$C" && out php workshop/bin/checkpoint salta cp-01) > "$WORK/carol-salta.txt" || die "salta cp-01"
[[ "$(git -C "$C" branch --show-current)" == "mio-cp-01" ]] && ok "carol su mio-cp-01" || die "branch carol"
git -C "$C" show mio-lavoro:appunti.txt | grep -q "appunti a metà" && ok "il branch vecchio conserva il lavoro (anche i file nuovi)" || die "lavoro di carol perso"
[[ ! -e "$C/.harness/state/sess.json" && -e "$C/.harness/state/heartbeat.json" ]] && ok "stato di sessione azzerato, heartbeat conservato" || die "stato"
grep -q 'Sei allineato a cp-01' "$WORK/carol-salta.txt" && ok "verify cp-01 verde dopo salta" || die "verify dopo salta"
(cd "$C" && runx php workshop/bin/checkpoint salta cp-01) && [[ "$(git -C "$C" branch --show-current)" == "mio-cp-01-2" ]] && ok "un secondo salto non tocca mio-cp-01" || die "secondo salto"

echo "dave: niente rete → bundle dalla chiavetta"
(cd "$WORK/build" && run php workshop/bin/release --bundle cp-01 --out="$WORK/usb") || die "bundle"
git bundle list-heads "$WORK/usb/cp-01.bundle" | grep -q 'refs/tags/cp-01' && ok "cp-01.bundle contiene refs/tags/cp-01" || die "bundle heads"
participant dave
D="$WORK/dave"
git -C "$D" tag -d cp-01 > /dev/null && git -C "$D" remote remove origin
(cd "$D" && runx php workshop/bin/checkpoint inizia E2 --bundle="$WORK/usb/cp-01.bundle") || die "inizia dal bundle"
[[ "$(git -C "$D" cat-file -t cp-01)" == "tag" ]] && ok "cp-01 importato come tag annotato" || die "tipo del tag"
[[ -f "$D/.harness/checkpoints/cp-02.php" ]] && ok "ingresso di E2 arrivato dal bundle" || die "ingresso dal bundle"

echo "guardrail"
git -C "$A" switch -q --detach
(cd "$A" && runx php workshop/bin/checkpoint inizia E2) && die "inizia in detached HEAD" || ok "rifiuta il detached HEAD"
git -C "$A" switch -q mio-lavoro
grep -E '^\$ git (reset|stash|clean|merge|rebase)|--detach' "$LOG" | grep -v 'switch -q' && die "comando distruttivo eseguito" || ok "nessun reset/stash/clean/merge/detach eseguito dagli script"

echo
echo "$pass controlli superati. Output: $LOG"
