import test from "node:test";
import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import { mkdtempSync, writeFileSync, mkdirSync, rmSync } from "node:fs";
import { tmpdir } from "node:os";
import { basename, dirname, join, resolve } from "node:path";

const bash = process.platform === "win32" ? "C:/Program Files/Git/bin/bash.exe" : "bash";
const installer = resolve("install.sh").replaceAll("\\", "/");
function run(code, args = []) {
    return spawnSync(bash, ["-c", 'source "$1"; ' + code, "installer-test", installer, ...args], { encoding: "utf8" });
}
function removeFixture(directory, prefix) {
    const target = resolve(directory);
    assert.equal(dirname(target), resolve(tmpdir()));
    assert.ok(basename(target).startsWith(prefix));
    rmSync(target, { recursive: true, force: true });
}

test("sourcing installer does not install or start anything", () => {
    const result = run("echo loaded");
    assert.equal(result.status, 0, result.stderr);
    assert.equal(result.stdout.trim(), "loaded");
});

test("Compose version gate accepts supported versions and rejects old or malformed output", () => {
    for (const [version, accepted] of [["2.24.4", true], ["v2.40.3", true], ["5.3.1", true], ["2.24.3", false], ["1.29.2", false], ["invalid", false]]) {
        const result = run('op_version_at_least "$2" 2 24 4', [version]);
        assert.equal(result.status === 0, accepted, version);
    }
});

test("automatic bootstrap recognizes Ubuntu and Debian and refuses unsupported hosts", () => {
    const directory = mkdtempSync(join(tmpdir(), "openpanel-os-"));
    try {
        for (const [id, version, codename, accepted] of [["ubuntu", "24.04", "noble", true], ["debian", "13", "trixie", true], ["arch", "rolling", "rolling", false], ["ubuntu", "20.04", "focal", false]]) {
            const file = join(directory, "os-release");
            writeFileSync(file, `ID=${id}\nVERSION_ID=${version}\nVERSION_CODENAME=${codename}\n`);
            const result = run('op_detect_os "$2"', [file.replaceAll("\\", "/")]);
            assert.equal(result.status === 0, accepted, result.stderr);
        }
    } finally { removeFixture(directory, "openpanel-os-"); }
});

test("working Docker installation is reused without package installation or privilege changes", () => {
    const directory = mkdtempSync(join(tmpdir(), "openpanel-ready-"));
    try {
        mkdirSync(join(directory, "bin"));
        writeFileSync(join(directory, "bin/docker"), '#!/bin/sh\ncase "$*" in\n "compose version --short") echo 2.40.3 ;;\n "buildx version"|info) exit 0 ;;\n *) exit 99 ;;\nesac\n', { mode: 0o755 });
        const result = run('cd "$2"; PATH="$PWD/bin:$PATH"; op_install_packages() { echo forbidden-package-change; return 99; }; op_root() { echo forbidden-privilege-change; return 99; }; op_prepare_dependencies', [directory.replaceAll("\\", "/")]);
        assert.equal(result.status, 0, result.stderr);
        assert.equal(result.stdout.trim(), "");
    } finally { removeFixture(directory, "openpanel-ready-"); }
});

test("daemon startup uses a service manager and sudo fallback without changing groups", () => {
    const directory = mkdtempSync(join(tmpdir(), "openpanel-daemon-"));
    try {
        mkdirSync(join(directory, "bin"));
        writeFileSync(join(directory, "bin/docker"), '#!/bin/sh\ncase "$*" in\n "compose version --short") echo 2.40.3 ;;\n "buildx version") exit 0 ;;\n info) exit 1 ;;\n *) exit 99 ;;\nesac\n', { mode: 0o755 });
        writeFileSync(join(directory, "bin/service"), "#!/bin/sh\nexit 99\n", { mode: 0o755 });
        const result = run('cd "$2"; PATH="$PWD/bin:$PATH"; op_root() { case "$*" in "docker info") test -f daemon-started ;; "service docker start"|"systemctl enable --now docker") touch daemon-started ;; *) echo forbidden-change; return 99 ;; esac; }; op_prepare_dependencies; echo "privileged=$OP_DOCKER_PRIVILEGED"', [directory.replaceAll("\\", "/")]);
        assert.equal(result.status, 0, result.stderr);
        assert.equal(result.stdout.trim(), "privileged=true");
    } finally { removeFixture(directory, "openpanel-daemon-"); }
});

test("conflicting runtime packages are preserved before any apt changes", () => {
    const directory = mkdtempSync(join(tmpdir(), "openpanel-conflict-"));
    try {
        mkdirSync(join(directory, "bin"));
        writeFileSync(join(directory, "bin/dpkg"), "#!/bin/sh\necho amd64\n", { mode: 0o755 });
        writeFileSync(join(directory, "bin/dpkg-query"), '#!/bin/sh\nfor arg; do last=$arg; done\nif [ "$last" = docker.io ]; then printf installed; fi\n', { mode: 0o755 });
        const result = run('cd "$2"; PATH="$PWD/bin:$PATH"; op_detect_os() { OP_OS_ID=ubuntu; OP_OS_CODENAME=noble; }; op_root() { echo forbidden-package-change; return 99; }; op_install_packages', [directory.replaceAll("\\", "/")]);
        assert.notEqual(result.status, 0);
        assert.match(result.stderr, /Conflicting package: docker.io/);
        assert.equal(result.stdout.trim(), "");
    } finally { removeFixture(directory, "openpanel-conflict-"); }
});
