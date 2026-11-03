#!/usr/bin/env bash
set -euo pipefail
umask 077

OP_DOCKER_PRIVILEGED=false

op_root() {
  if ((EUID == 0)); then "$@";
  elif command -v sudo >/dev/null 2>&1; then sudo "$@";
  else echo 'Root or sudo is required to install system dependencies.' >&2; return 1;
  fi
}

op_version_at_least() {
  local value=$1 major=$2 minor=$3 patch=$4
  [[ "$value" =~ ^v?([0-9]+)\.([0-9]+)\.([0-9]+) ]] || return 1
  local a=$((10#${BASH_REMATCH[1]})) b=$((10#${BASH_REMATCH[2]})) c=$((10#${BASH_REMATCH[3]}))
  ((a > major || (a == major && b > minor) || (a == major && b == minor && c >= patch)))
}

op_detect_os() {
  local release_file=${1:-/etc/os-release}
  [[ -r "$release_file" ]] || { echo 'Cannot identify Linux distribution.' >&2; return 1; }
  local ID='' VERSION_ID='' VERSION_CODENAME='' UBUNTU_CODENAME=''
  . "$release_file"
  OP_OS_ID=$ID
  OP_OS_CODENAME=${UBUNTU_CODENAME:-$VERSION_CODENAME}
  case "$ID:$VERSION_ID" in
    ubuntu:22.04|ubuntu:24.04|ubuntu:26.04|ubuntu:26.10|debian:12|debian:13) ;;
    *) echo 'Automatic dependency installation supports Ubuntu 22.04/24.04/26.04/26.10 and Debian 12/13. Other systems require working Docker, Compose, Buildx, Git and curl.' >&2; return 1 ;;
  esac
  [[ "$OP_OS_CODENAME" =~ ^[a-z][a-z0-9-]+$ ]] || { echo 'Invalid distribution codename.' >&2; return 1; }
}

op_tools_ready() {
  command -v git >/dev/null 2>&1 && command -v curl >/dev/null 2>&1 && command -v docker >/dev/null 2>&1 || return 1
  local compose_version
  compose_version=$(command docker compose version --short 2>/dev/null) || return 1
  op_version_at_least "$compose_version" 2 24 4 || return 1
  command docker buildx version >/dev/null 2>&1
}

op_dependency_report() {
  local tool
  for tool in git curl docker; do
    if command -v "$tool" >/dev/null 2>&1; then printf 'OK: %s\n' "$tool"; else printf 'MISSING: %s\n' "$tool"; fi
  done
  local compose_version
  compose_version=$(command docker compose version --short 2>/dev/null || true)
  if op_version_at_least "$compose_version" 2 24 4; then printf 'OK: Compose %s\n' "$compose_version"; else echo 'MISSING/OUTDATED: Compose >=2.24.4'; fi
  if command docker buildx version >/dev/null 2>&1; then echo 'OK: Buildx'; else echo 'MISSING: Buildx'; fi
}

# Installs packages only. Kept separate from daemon startup for container-based tests.
op_install_packages() {
  op_detect_os
  local architecture package status
  architecture=$(dpkg --print-architecture)
  case "$architecture" in amd64|arm64) ;; *) echo 'Automatic bootstrap supports amd64 and arm64.' >&2; return 1 ;; esac
  for package in docker.io docker-compose docker-compose-v2 docker-doc docker-buildx podman-docker containerd runc; do
    status=$(dpkg-query -W -f='${db:Status-Status}' "$package" 2>/dev/null || true)
    if [[ "$status" == installed ]]; then
      echo "Conflicting package: $package. Existing packages and workloads were preserved; resolve the conflict with your administrator before retrying." >&2
      return 1
    fi
  done
  op_root env DEBIAN_FRONTEND=noninteractive apt-get update
  op_root env DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends ca-certificates curl git
  if ! grep -Rsq "download.docker.com/linux/$OP_OS_ID" /etc/apt/sources.list /etc/apt/sources.list.d 2>/dev/null; then
    local temporary_key temporary_source
    temporary_key=$(mktemp)
    temporary_source=$(mktemp)
    if ! curl --proto '=https' --tlsv1.2 -fsSL --retry 3 --connect-timeout 15 "https://download.docker.com/linux/$OP_OS_ID/gpg" -o "$temporary_key"; then
      rm -f -- "$temporary_key" "$temporary_source"
      return 1
    fi
    printf 'Types: deb\nURIs: https://download.docker.com/linux/%s\nSuites: %s\nComponents: stable\nArchitectures: %s\nSigned-By: /etc/apt/keyrings/openpanel-docker.asc\n' "$OP_OS_ID" "$OP_OS_CODENAME" "$architecture" > "$temporary_source"
    op_root install -d -m 0755 /etc/apt/keyrings
    op_root install -m 0644 "$temporary_key" /etc/apt/keyrings/openpanel-docker.asc
    op_root install -m 0644 "$temporary_source" /etc/apt/sources.list.d/openpanel-docker.sources
    rm -f -- "$temporary_key" "$temporary_source"
  fi
  op_root env DEBIAN_FRONTEND=noninteractive apt-get update
  op_root env DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
}

op_prepare_dependencies() {
  if ! command -v git >/dev/null 2>&1 || ! command -v curl >/dev/null 2>&1; then
    op_detect_os
    op_root env DEBIAN_FRONTEND=noninteractive apt-get update
    op_root env DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends ca-certificates curl git
  fi
  if ! op_tools_ready; then
    echo 'Installing missing prerequisites from the official Docker apt repository.'
    op_install_packages
  fi
  op_tools_ready || { echo 'Dependency installation did not provide the required tools.' >&2; return 1; }
  if command docker info >/dev/null 2>&1; then return 0; fi
  if ! op_root docker info >/dev/null 2>&1; then
    if command -v systemctl >/dev/null 2>&1 && [[ -d /run/systemd/system ]]; then
      op_root systemctl enable --now docker
    elif command -v service >/dev/null 2>&1; then
      op_root service docker start
    else
      echo 'Docker is installed but no supported service manager can start it. Start the daemon and retry.' >&2
      return 1
    fi
  fi
  if command docker info >/dev/null 2>&1; then return 0; fi
  op_root docker info >/dev/null 2>&1 || { echo 'Docker daemon is unavailable.' >&2; return 1; }
  OP_DOCKER_PRIVILEGED=true
}

op_docker() {
  if [[ "$OP_DOCKER_PRIVILEGED" == true ]]; then op_root docker "$@"; else command docker "$@"; fi
}

main() {
# No PHP, Composer, Node or Laravel is required on the host.
check_only=false
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
    --check) check_only=true; shift ;;
    --private) private=true; shift ;;
    --help) echo 'Usage: bash install.sh [--domain panel.example.com | --ip 203.0.113.10] [--email admin@example.com] [--name Admin] [--private] [--check]'; exit 0 ;;
    *) echo "Unknown option: $1" >&2; exit 1 ;;
  esac
done
if [[ "$check_only" == true ]]; then
  op_dependency_report
  if ! op_tools_ready; then op_detect_os; echo 'Missing tools will be installed on this supported distribution.'; fi
  return 0
fi
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

op_prepare_dependencies

script_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
if [[ -f "$script_dir/composer.json" && -f "$script_dir/compose.yaml" ]]; then
  cd -- "$script_dir"
else
  command -v git >/dev/null || { echo 'Git is required to download the package.' >&2; exit 1; }
  target=${QOPENPANEL_DIR:-"$PWD/openpanel-community"}
  [[ ! -e "$target" ]] || { echo 'Destination already exists. Run install.sh from the existing project instead.' >&2; exit 1; }
  git clone --branch "${QOPENPANEL_VERSION:-v0.6.0-beta.1}" --depth 1 https://github.com/qualquer-de-tudo/openpanel-community.git "$target"
  cd -- "$target"
fi

# Bootstrap stays private until an operator creates the first account.
PANEL_BIND=127.0.0.1 PANEL_PORT=8080 PANEL_URL=http://127.0.0.1:8080 op_docker compose up -d --build
ready=false
for attempt in {1..60}; do
  if op_docker compose exec -T panel php artisan migrate:status >/dev/null 2>&1; then ready=true; break; fi
  sleep 2
done
[[ "$ready" == true ]] || { echo 'Startup failed. Inspect op_docker compose logs panel web.' >&2; exit 1; }

if [[ "$private" == true && -n "$admin_email" && -n "$admin_name" ]]; then
  op_docker compose exec -T panel php artisan qopenpanel:configure --name="$admin_name" --email="$admin_email"
fi

if [[ "$private" == false ]] && ! op_docker compose exec -T panel php artisan qopenpanel:install --check; then
  if op_docker compose exec -T panel php artisan qopenpanel:install --check-legacy; then
    echo 'Legacy 0.4 data detected. Back up, preserve APP_KEY and run qopenpanel:import-legacy --force before creating an account. See docs/MODULE.md.' >&2
    exit 1
  fi
  [[ -n "$admin_email" ]] || read -r -p 'Administrator email: ' admin_email
  [[ -n "$admin_name" ]] || read -r -p 'Administrator name: ' admin_name
  op_docker compose exec panel php artisan qopenpanel:configure --name="$admin_name" --email="$admin_email"
  echo 'Choose the account password in the following hidden terminal prompt.'
  op_docker compose exec panel php artisan panel:account "$admin_email" --name="$admin_name"
elif [[ "$private" == true ]]; then
  echo 'Private installation ready. Create the first account at http://127.0.0.1:8080/qopenpanel/login through an SSH tunnel.'
  echo 'Tunnel: ssh -L 8080:127.0.0.1:8080 operator@SERVER_IP'
  exit 0
fi

mkdir -p backups
[[ ! -f deploy.env ]] || cp -p deploy.env "backups/deploy-$(date +%Y%m%d-%H%M%S).env"
if [[ -n "$domain" ]]; then
  printf 'PANEL_DOMAIN=%s\n' "$domain" > deploy.env
  op_docker compose --env-file deploy.env -f compose.yaml -f compose.production.yaml config --quiet
  op_docker compose --env-file deploy.env -f compose.yaml -f compose.production.yaml up -d
  echo "QOpenPanel: https://$domain/qopenpanel (DNS must already point to this server; ports 80 and 443 must be reachable.)"
elif [[ -n "$address" ]]; then
  printf 'PANEL_BIND=0.0.0.0\nPANEL_PORT=8080\nPANEL_URL=http://%s:8080\n' "$address" > deploy.env
  op_docker compose --env-file deploy.env up -d
  echo "QOpenPanel: http://$address:8080/qopenpanel"
  echo 'IP mode uses HTTP. Restrict access to a trusted network or use an SSH tunnel until HTTPS is configured.'
else
  echo 'QOpenPanel: http://127.0.0.1:8080/qopenpanel (use an SSH tunnel for remote access).'
fi

}

if [[ "${BASH_SOURCE[0]}" == "$0" ]]; then main "$@"; fi
