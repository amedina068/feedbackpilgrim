#!/usr/bin/env bash
#
# Tests for install.sh. The tests put stub commands (docker, sudo, curl, id,
# uname) first in PATH, so they need no Docker and change nothing.
#
#   bash tests/install/installer-test.sh

# The stub bodies are single-quoted on purpose: the stub expands them, not this script.
# shellcheck disable=SC2016

set -euo pipefail


ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
INSTALLER="$ROOT/install.sh"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

PASSED=0
FAILED=0

# A bin folder with links to the real tools, but with no docker, sudo or curl.
# Each test adds its own stubs to it.
make_bin() {
    local bin="$WORK/$1/bin"
    mkdir -p "$bin"
    local tool path
    for tool in bash sh env cat sed grep awk tr date mktemp rm sort head tail cut wc sleep printf id uname getent ss nc touch dirname basename; do
        path="$(type -P "$tool" 2>/dev/null || true)"
        [ -n "$path" ] && ln -sf "$path" "$bin/$tool"
    done
    printf '%s' "$bin"
}

stub() {
    local bin="$1" name="$2" body="$3"
    # Remove the link first. A write through the link changes the real tool.
    rm -f "${bin:?}/$name"
    printf '#!/usr/bin/env bash\n%s\n' "$body" >"$bin/$name"
    chmod +x "$bin/$name"
}

# Runs the installer with the stub bin folder as the only PATH.
run_installer() {
    local bin="$1"
    shift
    PATH="$bin" FP_LOG_FILE="$WORK/install.log" NO_COLOR=1 bash "$INSTALLER" "$@" </dev/null 2>&1 || true
}

assert_contains() {
    local name="$1" output="$2" expected="$3"
    if [[ "$output" == *"$expected"* ]]; then
        PASSED=$((PASSED + 1))
        printf 'ok    %s\n' "$name"
    else
        FAILED=$((FAILED + 1))
        printf 'FAIL  %s\n      expected: %s\n      output:\n%s\n' "$name" "$expected" "$output"
    fi
}

assert_not_contains() {
    local name="$1" output="$2" unexpected="$3"
    if [[ "$output" != *"$unexpected"* ]]; then
        PASSED=$((PASSED + 1))
        printf 'ok    %s\n' "$name"
    else
        FAILED=$((FAILED + 1))
        printf 'FAIL  %s\n      did not expect: %s\n      output:\n%s\n' "$name" "$unexpected" "$output"
    fi
}

linux_user() {
    local bin="$1"
    stub "$bin" uname 'case "${1:-}" in -s) echo Linux ;; -m) echo x86_64 ;; *) echo Linux ;; esac'
    stub "$bin" id 'if [ "${1:-}" = "-u" ]; then echo 1000; elif [ "${1:-}" = "-un" ]; then echo deploy; else echo "uid=1000(deploy)"; fi'
    stub "$bin" sudo 'FP_TEST_SUDO=1 exec "$@"'
}

# A docker stub. It answers like a Docker that runs. With DENY_WITHOUT_SUDO=1,
# it refuses the socket unless sudo runs it.
docker_stub() {
    local bin="$1" deny="${2:-0}"
    stub "$bin" docker "
if [ \"$deny\" = 1 ] && [ -z \"\${FP_TEST_SUDO:-}\" ]; then
    echo 'permission denied while trying to connect to the Docker daemon socket' >&2
    exit 1
fi
case \"\$1\" in
    info) echo 27.3.1 ;;
    compose) echo 2.29.7 ;;
    container) exit 1 ;;
    *) exit 0 ;;
esac"
}

test_missing_docker_without_consent_stops_with_a_hint() {
    local bin
    bin="$(make_bin missing-no-consent)"
    linux_user "$bin"
    stub "$bin" curl 'exit 0'
    local output
    output="$(run_installer "$bin" --non-interactive --domain=localhost)"
    assert_contains "missing Docker: the install stops" "$output" "Error: Docker is not installed."
    assert_contains "missing Docker: the hint names --install-docker" "$output" "--install-docker"
}

test_missing_docker_and_curl_asks_for_curl() {
    local bin
    bin="$(make_bin missing-curl)"
    linux_user "$bin"
    local output
    output="$(run_installer "$bin" --non-interactive --install-docker --domain=localhost)"
    assert_contains "missing curl: asks for curl first" "$output" "curl is not installed"
}

test_missing_docker_with_the_flag_runs_get_docker() {
    local bin
    bin="$(make_bin missing-flag)"
    linux_user "$bin"
    stub "$bin" curl 'exit 0'
    local output
    output="$(run_installer "$bin" --non-interactive --install-docker --domain=localhost --dry-run)"
    assert_contains "--install-docker: downloads get.docker.com" "$output" "curl -fsSL https://get.docker.com -o"
    assert_contains "--install-docker: runs the Docker script as root" "$output" "as_root sh"
    assert_contains "--install-docker: tells that the install takes time" "$output" "Install Docker. This takes one or two minutes."
}

test_missing_docker_on_macos_points_to_docker_desktop() {
    local bin
    bin="$(make_bin missing-macos)"
    stub "$bin" uname 'case "${1:-}" in -s) echo Darwin ;; -m) echo arm64 ;; *) echo Darwin ;; esac'
    local output
    output="$(run_installer "$bin" --non-interactive --install-docker --domain=localhost)"
    assert_contains "macOS: points to Docker Desktop" "$output" "Install Docker Desktop"
    assert_not_contains "macOS: does not run get.docker.com" "$output" "Install Docker. This takes"
}

test_a_user_without_socket_access_gets_sudo() {
    local bin
    bin="$(make_bin socket-denied)"
    linux_user "$bin"
    docker_stub "$bin" 1
    local output
    output="$(run_installer "$bin" --non-interactive --domain=localhost --dry-run)"
    assert_contains "socket denied: switches to sudo" "$output" "The script uses sudo for the docker commands."
    assert_contains "socket denied: Docker answers with sudo" "$output" "Docker: 27.3.1"
    assert_contains "socket denied: the summary shows sudo docker" "$output" "sudo docker exec feedbackpilgrim"
}

test_a_user_with_socket_access_does_not_use_sudo() {
    local bin
    bin="$(make_bin socket-ok)"
    linux_user "$bin"
    docker_stub "$bin" 0
    local output
    output="$(run_installer "$bin" --non-interactive --domain=localhost --dry-run)"
    assert_contains "socket ok: Docker answers" "$output" "Docker: 27.3.1"
    assert_not_contains "socket ok: no sudo" "$output" "sudo docker"
}

test_missing_docker_without_consent_stops_with_a_hint
test_missing_docker_and_curl_asks_for_curl
test_missing_docker_with_the_flag_runs_get_docker
test_missing_docker_on_macos_points_to_docker_desktop
test_a_user_without_socket_access_gets_sudo
test_a_user_with_socket_access_does_not_use_sudo

printf '\n%d passed, %d failed\n' "$PASSED" "$FAILED"
[ "$FAILED" -eq 0 ]
