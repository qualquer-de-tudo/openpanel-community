#!/bin/sh
set -eu
exec bash "$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)/install.sh" "$@"
