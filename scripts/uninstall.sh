#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BACKUP_HOST_DIR="${BACKUP_HOST_DIR:-/storage/wallet-master-backups}"
COMPOSE_FILE="${ROOT}/docker-compose.prod.yml"
IMAGE="wallet-master-app:prod"
CONTAINERS=(wallet-app wallet-mysql wallet-redis wallet-typesense)

ASSUME_YES=false
for arg in "$@"; do
    case "$arg" in
        --yes|-y)
            ASSUME_YES=true
            ;;
        --help|-h)
            echo "Usage: $0 [--yes]"
            echo "Removes wallet-master containers, volumes, image, app directory, and Spatie backups."
            exit 0
            ;;
        *)
            echo "ERROR: Unknown argument: ${arg}" >&2
            echo "Usage: $0 [--yes]" >&2
            exit 1
            ;;
    esac
done

echo "==> wallet-master uninstall"
echo "    App directory : ${ROOT}"
echo "    Backup dir    : ${BACKUP_HOST_DIR}"
echo "    Containers    : ${CONTAINERS[*]}"
echo "    Image         : ${IMAGE}"
echo ""
echo "This permanently deletes the database, Redis/Typesense data, app files, and Spatie backups."
echo "The shared Docker network 'proxy' is left intact."
echo ""

if [[ "${ASSUME_YES}" != true ]]; then
    read -r -p "Type 'yes' to continue: " confirm
    if [[ "${confirm}" != "yes" ]]; then
        echo "Aborted."
        exit 1
    fi
fi

remove_named_containers() {
    local name
    for name in "${CONTAINERS[@]}"; do
        if docker ps -a --format '{{.Names}}' | grep -qx "${name}"; then
            echo "==> Removing container ${name}..."
            docker rm -f "${name}" >/dev/null
        fi
    done
}

if [[ -f "${COMPOSE_FILE}" ]]; then
    echo "==> Stopping compose stack (volumes included)..."
    (
        cd "${ROOT}"
        docker compose -f docker-compose.prod.yml down -v --remove-orphans
    )
else
    echo "==> Compose file missing; removing containers by name..."
    remove_named_containers
fi

# Catch leftovers if compose project name differed or file was already gone.
remove_named_containers

echo "==> Removing image ${IMAGE} (if present)..."
docker rmi "${IMAGE}" 2>/dev/null || true

echo "==> Removing leftover wallet volumes (if any)..."
mapfile -t leftover_volumes < <(
    docker volume ls --format '{{.Name}}' | grep -E '^(wallet-master_)?wallet-(mysql|redis|typesense)$' || true
)
if [[ "${#leftover_volumes[@]}" -gt 0 ]]; then
    docker volume rm "${leftover_volumes[@]}"
fi

PARENT="$(dirname "${ROOT}")"
echo "==> Removing app directory ${ROOT}..."
cd "${PARENT}"
rm -rf "${ROOT}"

if [[ -e "${BACKUP_HOST_DIR}" ]]; then
    echo "==> Removing backups ${BACKUP_HOST_DIR}..."
    rm -rf "${BACKUP_HOST_DIR}"
else
    echo "==> Backup directory already absent: ${BACKUP_HOST_DIR}"
fi

echo ""
echo "==> Done. wallet-master is removed from this host."
echo "    Manual follow-up: delete Cloudflare A record 'budget' (Proxied) for budget.kostecki.dev"
echo "    Verify: curl -I https://budget.kostecki.dev  (should fail to resolve or reach the app)"
