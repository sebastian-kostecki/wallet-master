#!/usr/bin/env bash

PRODUCTION_BRANCH="${PRODUCTION_BRANCH:-master}"

production_assert_branch() {
    local current_branch
    current_branch="$(git rev-parse --abbrev-ref HEAD)"

    if [[ "${current_branch}" != "${PRODUCTION_BRANCH}" ]]; then
        echo "ERROR: Production deploy must run on branch '${PRODUCTION_BRANCH}', currently on '${current_branch}'." >&2
        echo "Run: git checkout ${PRODUCTION_BRANCH}" >&2
        exit 1
    fi
}

production_pull_latest() {
    echo "==> Fetching and pulling ${PRODUCTION_BRANCH}..."
    git fetch origin "${PRODUCTION_BRANCH}"
    production_assert_branch
    git pull --ff-only origin "${PRODUCTION_BRANCH}"
}
