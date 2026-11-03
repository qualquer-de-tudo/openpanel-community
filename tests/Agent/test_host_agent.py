import importlib.util
import json
import os
from pathlib import Path
import tempfile
import types
import unittest
from unittest.mock import patch
import uuid

spec = importlib.util.spec_from_file_location("host_agent", Path(__file__).resolve().parents[2] / "bin/host-agent.py")
agent = importlib.util.module_from_spec(spec)
spec.loader.exec_module(agent)


class AgentTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(prefix="openpanel-agent-")
        self.root = Path(self.temporary.name)
        for directory in ["control/requests", "control/reports", "backups", "work", "releases", "processing", "current/resources/data", "config"]:
            (self.root / directory).mkdir(parents=True, exist_ok=True)
        (self.root / "current/resources/data/release.json").write_text('{"version":"0.7.0-beta.1"}')
        self.config = {"project": "openpanel", "project_dir": str(self.root / "current"), "deployment_dir": str(self.root / "current")}
        self.config_path = self.root / "config/maintenance.json"
        self.config_path.write_text(json.dumps(self.config))
        self.config_path.chmod(0o600)
        self.patches = [patch.object(agent, "ROOT", self.root), patch.object(agent, "CONFIG", self.config_path), patch.object(agent, "AGENT", self.root / "installed-agent.py")]
        for item in self.patches:
            item.start()
        self.job = {"schema": 1, "id": str(uuid.uuid4()), "action": "update", "version": "v0.8.0-beta.1", "sha": "f" * 40, "created_at": 1000}

    def tearDown(self):
        for item in reversed(self.patches):
            item.stop()
        self.temporary.cleanup()

    def test_request_protocol_rejects_arbitrary_commands_paths_and_expired_jobs(self):
        for changes in [{"command": "touch /etc/unsafe"}, {"action": "shell"}, {"version": "../../etc"}, {"sha": "not-a-sha"}, {"created_at": 1}, {"schema": True}, {"id": "../../config"}]:
            with self.subTest(changes=changes), self.assertRaises(agent.Failure):
                agent.validate_job({**self.job, **changes}, self.job["id"] + ".json", now=1000)
        self.assertEqual(agent.validate_job(self.job, self.job["id"] + ".json", now=1000), self.job)

    def test_version_order_distinguishes_beta_rc_and_stable_and_rejects_ranges(self):
        self.assertLess(agent.version("v1.0.0-beta.2"), agent.version("v1.0.0-rc.1"))
        self.assertLess(agent.version("v1.0.0-rc.1"), agent.version("v1.0.0"))
        self.assertLess(agent.version("v1.0.9"), agent.version("v1.1.0"))
        for value in ["latest", "^1.0", "v01.0.0", "v1.0.0;id"]:
            with self.assertRaises(agent.Failure):
                agent.version(value)

    def test_root_reports_are_readable_but_configuration_stays_private(self):
        report = self.root / "control/agent.json"
        agent.atomic_json(report, {"timestamp": 1})
        self.assertEqual(report.stat().st_mode & 0o777, 0o640)
        self.assertEqual(report.stat().st_gid, 1000)
        agent.atomic_json(self.config_path, self.config, 0o600)
        self.assertEqual(self.config_path.stat().st_mode & 0o777, 0o600)

    def test_scan_summary_does_not_return_paths_credentials_or_unrecognized_fields(self):
        data = {"Metadata": {"OS": {"Family": "ubuntu", "EOSL": True}}, "Secret": "never-return", "Results": [{"Class": "os-pkgs", "Type": "ubuntu", "Target": "/private/client", "Vulnerabilities": [{"VulnerabilityID": "CVE-2026-12345", "PkgName": "openssl", "InstalledVersion": "3.0", "FixedVersion": "3.1", "Severity": "HIGH", "Secret": "never-return"}]}]}
        result = agent.summarize_scan(data)
        self.assertEqual(result["counts"]["HIGH"], 1)
        self.assertTrue(result["eosl"])
        self.assertNotIn("never-return", json.dumps(result))
        self.assertNotIn("/private/client", json.dumps(result))

    def test_missing_os_is_unknown_and_never_a_successful_empty_scan(self):
        for family in (None, "none", "unknown", "unsupported"):
            with self.subTest(family=family), self.assertRaises(agent.Failure):
                agent.summarize_scan({"Metadata": {"OS": {"Family": family}}, "Results": []})
        with self.assertRaisesRegex(agent.Failure, "missing_package_inventory"):
            agent.summarize_scan({"Metadata": {"OS": {"Family": "ubuntu"}}, "Results": []})

    def test_scan_summary_counts_every_finding_but_bounds_returned_rows(self):
        result = agent.summarize_scan({"Metadata": {"OS": {"Family": "debian"}}, "Results": [{"Class": "os-pkgs", "Type": "debian", "Vulnerabilities": [{"VulnerabilityID": "CVE-2026-12345", "Severity": "HIGH"}] * 600}]})
        self.assertEqual(result["counts"]["HIGH"], 600)
        self.assertEqual(len(result["findings"]), 500)
        self.assertTrue(result["truncated"])

    def test_symlinks_and_fifos_are_refused_without_executing_a_job(self):
        target = self.root / "private"
        target.write_text("not-to-be-read")
        for kind in ["link", "fifo"]:
            request = self.root / "control/requests" / (str(uuid.uuid4()) + ".json")
            if kind == "link":
                request.symlink_to(target)
            else:
                os.mkfifo(request)
            with patch.object(agent, "run") as execute:
                agent.tick()
                execute.assert_not_called()
            self.assertFalse(request.exists())
            self.assertEqual(target.read_text(), "not-to-be-read")

    def test_completed_job_cannot_be_replayed_or_have_its_result_overwritten(self):
        name = self.job["id"] + ".json"
        report = self.root / "control/reports" / name
        report.write_text('{"status":"completed"}')
        (self.root / "control/requests" / name).write_text(json.dumps(self.job))
        with patch.object(agent, "run") as execute:
            agent.tick()
            execute.assert_not_called()
        self.assertEqual(json.loads(report.read_text())["status"], "completed")

    def exercise_update(self, failure=None):
        commands = []
        def fake_run(args, **options):
            commands.append(args)
            if args[:2] == ["git", "clone"]:
                directory = Path(args[-1])
                (directory / "resources/data").mkdir(parents=True)
                (directory / "resources/data/release.json").write_text('{"version":"0.8.0-beta.1"}')
                (directory / "bin").mkdir()
                (directory / "bin/host-agent.py").write_text("# official agent\n")
            elif args[:3] == ["git", "rev-parse", "HEAD"]:
                return "f" * 40
            elif args[-1] == "build" and failure == "build":
                raise agent.Failure("command_failed")
            elif "qopenpanel:maintenance-health" in args and failure in ["health", "restore"]:
                raise agent.Failure("health_check_failed")
            elif "wget" in args:
                return 'data-qopenpanel-path="/qopenpanel" /vendor/qopenpanel/assets/app.js'
            elif "up" in args and not any(str(value).endswith("-private.yaml") for value in args) and failure == "promotion":
                raise agent.Failure("command_failed")
            elif args[-1] == "tar -C /data -xzf /backup.tar.gz" and failure == "restore":
                raise agent.Failure("command_failed")
            if options.get("output"):
                options["output"].write(b"test-backup")
        def fake_api(path):
            if "/releases/" in path:
                return {"tag_name": self.job["version"], "draft": False}
            if "/actions/" in path:
                return {"workflow_runs": [{"name": "CI", "conclusion": "success", "head_sha": "f" * 40}]}
            return {"sha": "f" * 40}
        with patch.object(agent, "run", side_effect=fake_run), patch.object(agent, "api", side_effect=fake_api), patch.object(agent, "container_state", return_value=({"panel": "sha256:" + "a" * 64, "web": "sha256:" + "b" * 64}, "openpanel_panel-data")), patch.object(agent.shutil, "disk_usage", return_value=types.SimpleNamespace(free=10 * 1024**3)), patch.object(agent.time, "sleep"):
            if failure:
                with self.assertRaises(agent.Failure) as error:
                    agent.update(self.config, self.job)
                return commands, str(error.exception)
            return commands, agent.update(self.config, self.job)

    def test_successful_upgrade_preserves_project_and_creates_backup_before_candidate_start(self):
        commands, result = self.exercise_update()
        self.assertEqual(result["version"], self.job["version"])
        self.assertTrue((self.root / "backups" / result["backup"]).exists())
        self.assertEqual(json.loads(self.config_path.read_text())["project"], "openpanel")
        backup = next(index for index, command in enumerate(commands) if command[-1] == "tar -C /data -czf - .")
        candidate = next(index for index, command in enumerate(commands) if "up" in command)
        self.assertLess(backup, candidate)
        self.assertTrue(any("-private.yaml" in " ".join(command) for command in commands))

    def test_build_failure_does_not_stop_the_running_deployment(self):
        commands, code = self.exercise_update("build")
        self.assertEqual(code, "command_failed")
        self.assertFalse(any("stop" in command for command in commands))

    def test_failed_health_restores_backup_and_original_images(self):
        commands, code = self.exercise_update("health")
        self.assertEqual(code, "update_rolled_back")
        self.assertTrue(any(command[-1] == "tar -C /data -xzf /backup.tar.gz" for command in commands))
        self.assertTrue(any("-rollback.yaml" in " ".join(command) for command in commands))

    def test_failed_restore_requires_human_recovery(self):
        _, code = self.exercise_update("restore")
        self.assertEqual(code, "recovery_required")

    def test_promotion_failure_never_restores_old_database_after_possible_public_writes(self):
        commands, code = self.exercise_update("promotion")
        self.assertEqual(code, "recovery_required")
        self.assertFalse(any(command[-1] == "tar -C /data -xzf /backup.tar.gz" for command in commands))

    def test_downgrades_and_changed_release_sha_are_refused_before_build(self):
        with patch.object(agent, "api") as api:
            with self.assertRaisesRegex(agent.Failure, "not_an_upgrade"):
                agent.update(self.config, {**self.job, "version": "v0.6.0-beta.1"})
            api.assert_not_called()
        with patch.object(agent, "api", return_value={"draft": False, "tag_name": self.job["version"], "sha": "a" * 40}), patch.object(agent, "run") as execute:
            with self.assertRaisesRegex(agent.Failure, "release_changed"):
                agent.update(self.config, self.job)
            execute.assert_not_called()

    def test_release_without_successful_ci_is_not_executed(self):
        def fake_api(path):
            if "/releases/" in path:
                return {"tag_name": self.job["version"], "draft": False}
            if "/actions/" in path:
                return {"workflow_runs": [{"name": "CI", "conclusion": "failure", "head_sha": "f" * 40}]}
            return {"sha": "f" * 40}
        with patch.object(agent, "api", side_effect=fake_api), patch.object(agent, "run") as execute:
            with self.assertRaisesRegex(agent.Failure, "untested_release"):
                agent.update(self.config, self.job)
            execute.assert_not_called()


if __name__ == "__main__":
    unittest.main()
