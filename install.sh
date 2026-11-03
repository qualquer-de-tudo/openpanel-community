#!/usr/bin/env bash
set -euo pipefail
umask 077

# No PHP, Composer, Node or Laravel is required on the host.
domain=''
address=''
private=false
admin_email=''
admin_name=''
while (($#)); do
  case "$1" in
    --domain|--ip|--email|--name)
      (($# >= 2)) || { echo "Missing value: $1" >&2; exit 1; }
      case "$1" in
        --domain) domain=$2 ;; --ip) address=$2 ;; --email) admin_email=$2 ;; --name) admin_name=$2 ;;
      esac
      shift 2 ;;
    --private) private=true; shift ;;
    --help) echo 'Usage: bash install.sh [--domain panel.example.com | --ip 203.0.113.10] [--email admin@example.com] [--name Admin] [--private]'; exit 0 ;;
    *) echo "Unknown option: $1" >&2; exit 1 ;;
  esac
done
command -v docker >/dev/null || { echo 'Install Docker Engine and the Compose plugin first: https://docs.docker.com/engine/install/' >&2; exit 1; }
docker compose version >/dev/null
docker info >/dev/null
if [[ -n "$domain" && -n "$address" ]]; then echo 'Choose --domain or --ip.' >&2; exit 1; fi
if [[ -n "$domain" ]]; then
  [[ ${#domain} -le 253 && "$domain" == *.* && "$domain" =~ ^[a-zA-Z0-9]([a-zA-Z0-9.-]*[a-zA-Z0-9])?$ && "$domain" != *..* && "$domain" != *.-* && "$domain" != *-.* ]] || { echo 'Invalid DNS hostname.' >&2; exit 1; }
  IFS=. read -ra labels <<< "$domain"
  for label in "${labels[@]}"; do ((${#label} <= 63)) || { echo "DNS label too long." >&2; exit 1; }; done
fi
if [[ -n "$address" ]]; then
  [[ "$address" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}$ ]] || { echo 'Use an IPv4 address with --ip.' >&2; exit 1; }
  IFS=. read -r a b c d <<< "$address"
  for octet in "$a" "$b" "$c" "$d"; do ((10#$octet <= 255)) || { echo 'Invalid IPv4 address.' >&2; exit 1; }; done
fi
if [[ ! -t 0 && "$private" == false ]]; then echo 'Interactive terminal required for account creation. Use --private for an unattended, loopback-only installation.' >&2; exit 1; fi

script_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
if [[ -f "$script_dir/composer.json" && -f "$script_dir/compose.yaml" ]]; then
  cd -- "$script_dir"
else
  command -v git >/dev/null || { echo 'Git is required to download the package.' >&2; exit 1; }
  target=${QOPENPANEL_DIR:-"$PWD/qopenpanel-community"}
  [[ ! -e "$target" ]] || { echo 'Destination already exists. Run install.sh from the existing project instead.' >&2; exit 1; }
  git clone --branch "${QOPENPANEL_VERSION:-v0.5.0-beta.1}" --depth 1 https://github.com/qualquer-de-tudo/qopenpanel-community.git "$target"
  cd -- "$target"
fi

# Bootstrap stays private until an operator creates the first account.
PANEL_BIND=127.0.0.1 PANEL_PORT=8080 PANEL_URL=http://127.0.0.1:8080 docker compose up -d --build
ready=false
for attempt in {1..60}; do
  if docker compose exec -T panel php artisan migrate:status >/dev/null 2>&1; then ready=true; break; fi
  sleep 2
done
[[ "$ready" == true ]] || { echo 'Startup failed. Inspect docker compose logs panel web.' >&2; exit 1; }

if [[ "$private" == true && -n "$admin_email" && -n "$admin_name" ]]; then
  docker compose exec -T panel php artisan qopenpanel:configure --name="$admin_name" --email="$admin_email"
fi

if [[ "$private" == false ]] && ! docker compose exec -T panel php artisan qopenpanel:install --check; then
  if docker compose exec -T panel php artisan qopenpanel:install --check-legacy; then
    echo 'Legacy 0.4 data detected. Back up, preserve APP_KEY and run qopenpanel:import-legacy --force before creating an account. See docs/MODULE.md.' >&2
    exit 1
  fi
  [[ -n "$admin_email" ]] || read -r -p 'Administrator email: ' admin_email
  [[ -n "$admin_name" ]] || read -r -p 'Administrator name: ' admin_name
  docker compose exec panel php artisan qopenpanel:configure --name="$admin_name" --email="$admin_email"
  echo 'Choose the account password in the following hidden terminal prompt.'
  docker compose exec panel php artisan panel:account "$admin_email" --name="$admin_name"
elif [[ "$private" == true ]]; then
  echo 'Private installation ready. Create the first account at http://127.0.0.1:8080/qopenpanel/login through an SSH tunnel.'
  echo 'Tunnel: ssh -L 8080:127.0.0.1:8080 operator@SERVER_IP'
  exit 0
fi

mkdir -p backups
[[ ! -f deploy.env ]] || cp -p deploy.env "backups/deploy-$(date +%Y%m%d-%H%M%S).env"
if [[ -n "$domain" ]]; then
  printf 'PANEL_DOMAIN=%s\n' "$domain" > deploy.env
  docker compose --env-file deploy.env -f compose.yaml -f compose.production.yaml config --quiet
  docker compose --env-file deploy.env -f compose.yaml -f compose.production.yaml up -d
  echo "QOpenPanel: https://$domain/qopenpanel (DNS must already point to this server; ports 80 and 443 must be reachable.)"
elif [[ -n "$address" ]]; then
  printf 'PANEL_BIND=0.0.0.0\nPANEL_PORT=8080\nPANEL_URL=http://%s:8080\n' "$address" > deploy.env
  docker compose --env-file deploy.env up -d
  echo "QOpenPanel: http://$address:8080/qopenpanel"
  echo 'IP mode uses HTTP. Restrict access to a trusted network or use an SSH tunnel until HTTPS is configured.'
else
  echo 'QOpenPanel: http://127.0.0.1:8080/qopenpanel (use an SSH tunnel for remote access).'
fi
