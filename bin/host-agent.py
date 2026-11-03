#!/usr/bin/env python3
"""Root-side maintenance broker. No shell input, transport credentials or Docker socket in the panel."""
import ast
import fcntl
import json
import os
from pathlib import Path
import re
import shutil
import stat
import subprocess
import sys
import time
import urllib.request
import uuid

API = "https://api.github.com/repos/qualquer-de-tudo/openpanel-community"
REPOSITORY = "https://github.com/qualquer-de-tudo/openpanel-community.git"
SCANNER = "aquasec/trivy@sha256:790fd66d1697395d8693d1acaa1ea702406a20f109b6fc7bac8db623eca6c06c"
TAG = re.compile(r"^v(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-(alpha|beta|rc)\.([1-9]\d*))?$")
ROOT = Path("/var/lib/openpanel")
CONFIG = Path("/etc/openpanel/maintenance.json")
AGENT = Path("/usr/local/lib/openpanel/host-agent.py")


class Failure(Exception):
    pass


def version(value):
    if not isinstance(value, str) or len(value) > 50:
        raise Failure("invalid_version")
    match = TAG.fullmatch(value)
    if not match:
        raise Failure("invalid_version")
    a, b, c, label, number = match.groups()
    return (int(a), int(b), int(c), {None: 3, "rc": 2, "beta": 1, "alpha": 0}[label], int(number or 0))


def run(args, *, cwd=None, capture=False, timeout=1200, output=None):
    try:
        result = subprocess.run(args, cwd=cwd, stdin=subprocess.DEVNULL, stdout=output or (subprocess.PIPE if capture else subprocess.DEVNULL), stderr=subprocess.DEVNULL, timeout=timeout, check=True)
    except (subprocess.SubprocessError, OSError):
        raise Failure("command_failed") from None
    if capture:
        if len(result.stdout) > 2097152:
            raise Failure("oversized_response")
        return result.stdout.decode("utf-8")


def atomic_json(path, value, mode=0o640):
    """Parent directories are root-owned; rename never follows a destination symlink."""
    temporary = path.parent / ("." + uuid.uuid4().hex)
    fd = os.open(temporary, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, mode)
    os.fchmod(fd, mode)
    if mode == 0o640:
        os.fchown(fd, -1, 1000)
    with os.fdopen(fd, "w", encoding="utf-8") as stream:
        json.dump(value, stream, ensure_ascii=True)
        stream.flush()
        os.fsync(stream.fileno())
    os.replace(temporary, path)


def api(path):
    request = urllib.request.Request(API + path, headers={"User-Agent": "OpenPanel-Maintenance/1", "Accept": "application/vnd.github+json"})
    try:
        with urllib.request.urlopen(request, timeout=20) as response:
            if not response.url.startswith(API + "/"):
                raise Failure("release_unavailable")
            body = response.read(2097153)
            if len(body) > 2097152:
                raise Failure("oversized_response")
            return json.loads(body)
    except (OSError, ValueError):
        raise Failure("release_unavailable") from None


def validate_job(job, name, now=None):
    if not isinstance(job, dict) or set(job) != {"schema", "id", "action", "version", "sha", "created_at"} or type(job["schema"]) is not int or job["schema"] != 1:
        raise Failure("invalid_request")
    try:
        identifier = str(uuid.UUID(job["id"]))
    except (ValueError, TypeError, AttributeError):
        raise Failure("invalid_request") from None
    if name != identifier + ".json" or job["id"] != identifier or type(job["created_at"]) is not int or abs((time.time() if now is None else now) - job["created_at"]) > 600:
        raise Failure("expired_or_invalid_request")
    if job["action"] == "update":
        version(job["version"] if isinstance(job["version"], str) else "")
        if not isinstance(job["sha"], str) or not re.fullmatch("[a-f0-9]{40}", job["sha"]):
            raise Failure("invalid_request")
    elif job["action"] != "scan" or job["version"] is not None or job["sha"] is not None:
        raise Failure("invalid_request")
    return job


def source_version(directory):
    data = json.loads((Path(directory) / "resources/data/release.json").read_text())
    value = "v" + data["version"]
    version(value)
    return value


def compose(config, source=None, overrides=()):
    directory = Path(source or config["project_dir"])
    deployment = Path(config["deployment_dir"])
    command = ["docker", "compose", "--project-name", config["project"], "--project-directory", str(directory)]
    environment = deployment / "deploy.env"
    domain = False
    if environment.is_file():
        command += ["--env-file", str(environment)]
        domain = any(line.startswith("PANEL_DOMAIN=") and line.partition("=")[2].strip() for line in environment.read_text().splitlines())
    command += ["-f", str(directory / "compose.yaml")]
    if domain:
        command += ["-f", str(directory / "compose.production.yaml")]
    command += ["-f", str(deployment / "compose.maintenance.yaml")]
    for override in overrides:
        command += ["-f", str(override)]
    return command


def container_state(config):
    images = {}
    volume = None
    for service in ("panel", "web"):
        identifier = run(compose(config) + ["ps", "-q", service], capture=True).strip()
        if not re.fullmatch("[a-f0-9]{64}", identifier):
            raise Failure("deployment_unavailable")
        info = json.loads(run(["docker", "inspect", identifier], capture=True))[0]
        image = info["Image"]
        if not re.fullmatch("sha256:[a-f0-9]{64}", image):
            raise Failure("deployment_unavailable")
        images[service] = image
        if service == "panel":
            volume = next((mount["Name"] for mount in info["Mounts"] if mount["Type"] == "volume" and mount["Destination"] == "/data"), None)
    if not volume or not re.fullmatch("[a-zA-Z0-9_.-]+", volume):
        raise Failure("unsupported_data_volume")
    return images, volume


def authorize(config, job):
    try:
        run(compose(config) + ["exec", "-T", "panel", "php", "artisan", "qopenpanel:maintenance-authorize", "--", job["id"], job["action"], job["version"] or "-", job["sha"] or "-"], timeout=30)
    except Failure:
        raise Failure("authorization_revoked_or_unavailable") from None


def update(config, job):
    target = job["version"]
    if version(target) <= version(source_version(config["project_dir"])):
        raise Failure("not_an_upgrade")
    release = api("/releases/tags/" + target)
    commit = api("/commits/" + target).get("sha")
    if release.get("draft", True) or release.get("tag_name") != target or commit != job["sha"]:
        raise Failure("release_changed")
    runs = api("/actions/runs?head_sha=" + commit + "&per_page=10").get("workflow_runs", [])
    if not any(item.get("head_sha") == commit and item.get("name") == "CI" and item.get("conclusion") == "success" for item in runs):
        raise Failure("untested_release")
    if shutil.disk_usage(ROOT).free < 2147483648:
        raise Failure("insufficient_disk")
    checkout = ROOT / "releases" / (target + "-" + job["id"])
    run(["git", "clone", "--depth", "1", "--single-branch", "--branch", target, REPOSITORY, str(checkout)])
    if run(["git", "rev-parse", "HEAD"], cwd=checkout, capture=True).strip() != commit or source_version(checkout) != target:
        raise Failure("release_changed")
    agent_source = (checkout / "bin/host-agent.py").read_text()
    try:
        ast.parse(agent_source)
    except SyntaxError:
        raise Failure("invalid_release") from None
    for name in ("deploy.env", "compose.maintenance.yaml"):
        origin = Path(config["deployment_dir"]) / name
        if origin.is_file():
            shutil.copy2(origin, checkout / name)
    images, volume = container_state(config)
    for service, image in images.items():
        run(["docker", "tag", image, "openpanel-backup:" + job["id"] + "-" + service])
    run(compose(config, checkout) + ["build"])
    authorize(config, job)
    backup = ROOT / "backups" / (job["id"] + ".tar.gz")
    private_ports = ROOT / "work" / (job["id"] + "-private.yaml")
    private_ports.write_text('services:\n  web:\n    ports: !override []\n')
    rollback = ROOT / "work" / (job["id"] + "-rollback.yaml")
    rollback.write_text("services:\n" + "".join("  " + service + ":\n    image: " + image + "\n" for service, image in images.items()))
    stopped = False
    backed_up = False
    promoting = False
    try:
        stopped = True
        run(compose(config) + ["stop", "web", "panel"])
        with backup.open("xb") as stream:
            run(["docker", "run", "--rm", "--network", "none", "--mount", "type=volume,src=" + volume + ",dst=/data", "--entrypoint", "sh", images["panel"], "-c", "tar -C /data -czf - ."], output=stream)
        run(["gzip", "-t", str(backup)])
        backed_up = True
        run(compose(config, checkout, [private_ports]) + ["up", "-d", "--no-build"])
        _, candidate_volume = container_state({**config, "project_dir": str(checkout)})
        if candidate_volume != volume:
            raise Failure("unsupported_data_volume")
        healthy = False
        for _ in range(60):
            try:
                run(compose(config, checkout, [private_ports]) + ["exec", "-T", "panel", "php", "artisan", "qopenpanel:maintenance-health", target, "--job=" + job["id"], "--sha=" + job["sha"]], timeout=30)
                page = run(compose(config, checkout, [private_ports]) + ["exec", "-T", "web", "wget", "-q", "-O", "-", "http://127.0.0.1:9090/"], capture=True, timeout=30)
                # Root redirects to login; wget follows the internal redirect.
                if 'data-qopenpanel-path=' in page and '/vendor/qopenpanel/assets/' in page:
                    healthy = True
                    break
            except Failure:
                pass
            time.sleep(2)
        if not healthy:
            raise Failure("health_check_failed")
        promoting = True
        run(compose(config, checkout) + ["up", "-d", "--no-build"])
    except Failure:
        if promoting:
            # Once publication starts, traffic may have written new data. Never restore an older DB automatically.
            raise Failure("recovery_required") from None
        # Until promotion, the candidate has no published ports. Restore the entire SQLite/key backup.
        try:
            run(compose(config, checkout, [private_ports]) + ["stop", "web", "panel"])
            if backed_up:
                run(["docker", "run", "--rm", "--network", "none", "--mount", "type=volume,src=" + volume + ",dst=/data", "--mount", "type=bind,src=" + str(backup) + ",dst=/backup.tar.gz,readonly", "--entrypoint", "sh", images["panel"], "-c", "tar -C /data -xzf /backup.tar.gz"])
            if stopped:
                run(compose(config, overrides=[rollback]) + ["up", "-d", "--no-build"])
        except Failure:
            raise Failure("recovery_required") from None
        raise Failure("update_rolled_back") from None
    config["project_dir"] = str(checkout)
    config["deployment_dir"] = str(checkout)
    atomic_json(CONFIG, config, 0o600)
    destination = AGENT
    temporary = destination.with_suffix(".new")
    temporary.write_text(agent_source)
    temporary.chmod(0o644)
    os.replace(temporary, destination)
    return {"version": target, "backup": backup.name}


def summarize_scan(data):
    os_info = data.get("Metadata", {}).get("OS", {})
    if os_info.get("Family") not in ("ubuntu", "debian"):
        raise Failure("unsupported_scan_target")
    results = [result for result in data.get("Results", []) if result.get("Class") == "os-pkgs" and result.get("Type") == os_info["Family"]]
    if not results:
        raise Failure("missing_package_inventory")
    counts = {key: 0 for key in ["CRITICAL", "HIGH", "MEDIUM", "LOW", "UNKNOWN"]}
    findings = []
    for result in results:
        for item in result.get("Vulnerabilities", []):
            severity = item.get("Severity", "UNKNOWN")
            severity = severity if severity in counts else "UNKNOWN"
            counts[severity] += 1
            if len(findings) >= 500:
                continue
            identifier = str(item.get("VulnerabilityID", ""))
            if not re.fullmatch(r"(?:CVE-\d{4}-\d{4,}|GHSA-[a-z0-9-]+)", identifier):
                continue
            clean = lambda value: re.sub(r"[^a-zA-Z0-9@._/+~:, \-]", "", str(value))[:160]
            findings.append({"id": identifier, "package": clean(item.get("PkgName", "")), "installed": clean(item.get("InstalledVersion", "")), "fixed": clean(item.get("FixedVersion", "")), "severity": severity})
    return {"status": "completed", "timestamp": int(time.time()), "os": str(os_info["Family"])[:40], "eosl": bool(os_info.get("EOSL", False)), "counts": counts, "findings": findings, "truncated": sum(counts.values()) > len(findings), "scope": "host-os-packages", "scanner": SCANNER}


def scan():
    report = ROOT / "work" / (uuid.uuid4().hex + ".json")
    with report.open("xb") as stream:
        run(["docker", "run", "--rm", "--cap-drop=ALL", "--security-opt=no-new-privileges", "--read-only", "--memory=1g", "--cpus=1", "--pids-limit=128", "--tmpfs", "/tmp:rw,noexec,nosuid,size=512m", "--mount", "type=bind,src=/,dst=/host,readonly", "--mount", "type=volume,src=openpanel-trivy-cache,dst=/cache", SCANNER, "rootfs", "--cache-dir", "/cache", "--scanners", "vuln", "--pkg-types", "os", "--offline-scan", "--list-all-pkgs=false", "--format", "json", "--skip-dirs", "/host/proc,/host/sys,/host/dev,/host/run,/host/var/lib/docker,/host/var/lib/openpanel", "/host"], output=stream, timeout=900)
    if report.stat().st_size > 33554432:
        raise Failure("oversized_response")
    result = summarize_scan(json.loads(report.read_text()))
    result["reboot_required"] = Path("/var/run/reboot-required").exists()
    try:
        ssh = dict(line.split(" ", 1) for line in run(["sshd", "-T"], capture=True, timeout=10).splitlines() if " " in line)
        result["ssh"] = {key: ssh.get(key, "unknown") for key in ("permitrootlogin", "passwordauthentication", "pubkeyauthentication")}
    except Failure:
        result["ssh"] = None
    report.unlink()
    return result


def configure(directory):
    if os.geteuid() != 0:
        raise Failure("root_required")
    directory = Path(directory).resolve(strict=True)
    model = json.loads(run(["docker", "compose", "config", "--format", "json"], cwd=directory, capture=True))
    project = model["name"]
    if CONFIG.exists():
        existing = json.loads(CONFIG.read_text())
        if str(directory) not in (existing["project_dir"], existing["deployment_dir"]):
            raise Failure("another_deployment_registered")
        project = existing["project"]
    if not re.fullmatch("[a-z0-9][a-z0-9_-]{0,100}", project):
        raise Failure("invalid_project")
    for name in ("control", "control/reports", "backups", "releases", "work", "processing"):
        (ROOT / name).mkdir(parents=True, exist_ok=True)
    ROOT.chmod(0o755)
    for name in ("control", "control/reports"):
        os.chown(ROOT / name, 0, 1000)
        (ROOT / name).chmod(0o750)
    for name in ("backups", "releases", "work", "processing"):
        (ROOT / name).chmod(0o700)
    (ROOT / "control/requests").mkdir(exist_ok=True)
    os.chown(ROOT / "control/requests", 1000, 1000)
    (ROOT / "control/requests").chmod(0o700)
    (directory / "compose.maintenance.yaml").write_text('services:\n  panel:\n    environment:\n      OPENPANEL_CONTROL_PATH: /control\n    volumes:\n      - /var/lib/openpanel/control:/control\n')
    (directory / "compose.maintenance.yaml").chmod(0o600)
    CONFIG.parent.mkdir(parents=True, exist_ok=True)
    atomic_json(CONFIG, {"schema": 1, "project": project, "project_dir": str(directory), "deployment_dir": str(directory)}, 0o600)


def tick():
    if os.geteuid() != 0 or CONFIG.is_symlink() or CONFIG.stat().st_uid != 0 or CONFIG.stat().st_mode & 0o022:
        raise Failure("unsafe_configuration")
    config = json.loads(CONFIG.read_text())
    control = ROOT / "control"
    with (ROOT / "agent.lock").open("a") as lock:
        try:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            return
        atomic_json(control / "agent.json", {"timestamp": int(time.time()), "version": source_version(config["project_dir"]), "scanner": SCANNER})
        for pending in list((ROOT / "processing").glob("*.json"))[:100]:
            atomic_json(control / "reports" / pending.name, {"status": "failed", "code": "recovery_required", "timestamp": int(time.time())})
            pending.rename(pending.with_suffix(".interrupted"))
        for entry in list((control / "requests").iterdir())[:100]:
            pending = None
            if not re.fullmatch(r"[a-f0-9-]{36}\.json", entry.name):
                continue
            if (control / "reports" / entry.name).exists():
                if entry.is_symlink() or not entry.is_dir():
                    entry.unlink(missing_ok=True)
                continue
            try:
                fd = os.open(entry, os.O_RDONLY | os.O_NOFOLLOW | os.O_NONBLOCK)
                with os.fdopen(fd) as stream:
                    details = os.fstat(stream.fileno())
                    if not stat.S_ISREG(details.st_mode) or details.st_size > 4096 or details.st_nlink != 1:
                        raise Failure("invalid_request")
                    job = validate_job(json.load(stream), entry.name)
                pending = ROOT / "processing" / entry.name
                entry.rename(pending)
                atomic_json(control / "reports" / entry.name, {"status": "running", "timestamp": int(time.time())})
                authorize(config, job)
                result = update(config, job) if job["action"] == "update" else scan()
                if job["action"] == "scan":
                    atomic_json(control / "security.json", result)
                atomic_json(control / "reports" / entry.name, {"status": "completed", "timestamp": int(time.time()), "result": result if job["action"] == "update" else {"scope": "host-os-packages"}})
                pending.unlink()
            except (Failure, OSError, ValueError, KeyError, TypeError) as error:
                code = str(error) if isinstance(error, Failure) else "operation_failed"
                atomic_json(control / "reports" / entry.name, {"status": "failed", "code": code, "timestamp": int(time.time())})
                if pending is not None and pending.exists():
                    pending.rename(pending.with_suffix(".failed"))
                elif entry.is_symlink() or not entry.is_dir():
                    entry.unlink()
            return


if __name__ == "__main__":
    os.umask(0o077)
    try:
        if len(sys.argv) == 2 and sys.argv[1] in ("--current", "--project"):
            if os.geteuid() != 0:
                raise Failure("root_required")
            saved = json.loads(CONFIG.read_text())
            print(saved["project_dir"] if sys.argv[1] == "--current" else saved["project"])
        elif len(sys.argv) == 3 and sys.argv[1] == "--check-target":
            if os.geteuid() != 0:
                raise Failure("root_required")
            saved = json.loads(CONFIG.read_text())
            if version(sys.argv[2]) < version(source_version(saved["project_dir"])):
                raise Failure("not_an_upgrade")
        elif len(sys.argv) == 3 and sys.argv[1] == "--configure":
            configure(sys.argv[2])
        elif len(sys.argv) == 1:
            tick()
        else:
            raise Failure("invalid_arguments")
    except (Failure, OSError, ValueError, KeyError) as error:
        print(str(error) if isinstance(error, Failure) else "agent_failed", file=sys.stderr)
        sys.exit(1)
