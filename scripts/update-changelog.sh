#!/bin/bash
# update-changelog.sh
# Gera uma nova entrada no CHANGELOG.md a partir do último commit.
# Uso: ./scripts/update-changelog.sh [versão]
# Exemplo: ./scripts/update-changelog.sh 0.4.0

set -euo pipefail

CHANGELOG="$(git rev-parse --show-toplevel)/CHANGELOG.md"
DATE=$(date +%Y-%m-%d)
VERSION="${1:-}"

if [[ ! -f "$CHANGELOG" ]]; then
  echo "Erro: CHANGELOG.md não encontrado em $(git rev-parse --show-toplevel)" >&2
  exit 1
fi

# Lê o último commit
LAST_MSG=$(git log -1 --format="%s")

# Extrai a letra (entre aspas simples) e o resto
LYRICS=""
REST=""

if [[ "$LAST_MSG" =~ ^lyrics:\ \'(.+)\'\ —\ (.+)$ ]]; then
  LYRICS="${BASH_REMATCH[1]}"
  REST="${BASH_REMATCH[2]}"
elif [[ "$LAST_MSG" =~ ^lyrics:\ \"(.+)\"\ —\ (.+)$ ]]; then
  LYRICS="${BASH_REMATCH[1]}"
  REST="${BASH_REMATCH[2]}"
else
  REST="$LAST_MSG"
fi

# Extrai tipo e descrição do resto (ex: "fix: remove arquivo morto")
TYPE=""
DESC=""
if [[ "$REST" =~ ^([a-z]+):\ (.+)$ ]]; then
  TYPE="${BASH_REMATCH[1]}"
  DESC="${BASH_REMATCH[2]}"
else
  TYPE="chore"
  DESC="$REST"
fi

# Mapeia tipo para seção do changelog
case "$TYPE" in
  feat)     SECTION="Added" ;;
  fix)      SECTION="Fixed" ;;
  refactor) SECTION="Changed" ;;
  docs)     SECTION="Changed" ;;
  test)     SECTION="Changed" ;;
  ci|infra) SECTION="Changed" ;;
  remove|rm) SECTION="Removed" ;;
  *)        SECTION="Changed" ;;
esac

# Monta o cabeçalho da versão
if [[ -n "$VERSION" ]]; then
  HEADER="## [$VERSION] — $DATE"
else
  HEADER="## [Unreleased] — $DATE"
fi

# Monta o bloco da entrada em um arquivo temporário
BLOCKFILE=$(mktemp)
if [[ -n "$LYRICS" ]]; then
  printf '%s\n\n> *"%s"*\n\n### %s\n- %s\n' \
    "$HEADER" "$LYRICS" "$SECTION" "$DESC" > "$BLOCKFILE"
else
  printf '%s\n\n### %s\n- %s\n' \
    "$HEADER" "$SECTION" "$DESC" > "$BLOCKFILE"
fi

# Insere após o primeiro "---" no CHANGELOG
TMPFILE=$(mktemp)
awk -v blockfile="$BLOCKFILE" '
  /^---$/ && !done {
    print "---"
    print ""
    while ((getline line < blockfile) > 0) print line
    done=1
    next
  }
  { print }
' "$CHANGELOG" > "$TMPFILE"

rm -f "$BLOCKFILE"
mv "$TMPFILE" "$CHANGELOG"

echo "CHANGELOG.md atualizado com:"
echo "  Versão : ${VERSION:-Unreleased}"
echo "  Data   : $DATE"
echo "  Tipo   : $TYPE ($SECTION)"
echo "  Desc   : $DESC"
if [[ -n "$LYRICS" ]]; then
  echo "  Letra  : \"$LYRICS\""
fi
