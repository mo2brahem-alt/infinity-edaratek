import gzip
import hashlib
import io
import json
import os
import tarfile
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

import review_bundle as bundle


def archive(name="app/file.php", content=b"safe", symlink=False):
    buffer = io.BytesIO()
    with tarfile.open(fileobj=buffer, mode="w") as output:
        entry = tarfile.TarInfo(name)
        if symlink:
            entry.type = tarfile.SYMTYPE
            entry.linkname = "/etc/fixture"
        else:
            entry.size = len(content)
        output.addfile(entry, None if symlink else io.BytesIO(content))
    return buffer.getvalue()


class ReviewBundleTests(unittest.TestCase):
    def test_exact_commit_required(self):
        for value in ("main", "../fixture", "a" * 39, "a" * 40 + "\n"):
            with self.subTest(value=value), self.assertRaises(ValueError):
                bundle.check_sha(value)
        bundle.check_sha("a" * 40)

    def test_safe_source_and_example_allowed(self):
        bundle.check_members(archive())
        bundle.check_members(archive(".env.example", b"EXAMPLE_ONLY="))

    def test_sensitive_paths_rejected(self):
        for name in (".env", "nested/.env.testing", "key.pem", "key.jks", "auth.json", "local.properties", "service-account-test.json", "credentials-test.json"):
            with self.subTest(name=name), self.assertRaises(ValueError):
                bundle.check_members(archive(name))

    def test_escape_and_symlink_rejected(self):
        for name in ("../escape", "/absolute"):
            with self.subTest(name=name), self.assertRaises(ValueError):
                bundle.check_members(archive(name))
        with self.assertRaises(ValueError):
            bundle.check_members(archive(symlink=True))

    def test_service_account_content_rejected(self):
        with self.assertRaises(ValueError):
            bundle.check_members(archive(content=json.dumps({"type": "service_" + "account"}).encode()))

    def test_dirty_checkout_rejected_without_git_writes(self):
        with patch.object(bundle.subprocess, "check_output", side_effect=["a" * 40, b" M fixture"]):
            with self.assertRaises(ValueError):
                bundle.run("create", "a" * 40, "unused")

    def test_round_trip_and_digest_tampering(self):
        with tempfile.TemporaryDirectory(prefix="edaratek-review-") as root:
            previous = Path.cwd()
            try:
                os.chdir(root)
                Path("composer.lock").write_bytes(b"fixture lock")
                raw = archive("composer.lock", b"fixture lock")
                with patch.object(bundle.subprocess, "check_output", side_effect=["a" * 40, b"", raw]):
                    bundle.run("create", "a" * 40, "out")
                with patch.object(bundle.subprocess, "check_output", return_value=raw):
                    bundle.run("verify", "a" * 40, "out")
                path = Path("out/release-manifest.json")
                manifest = json.loads(path.read_text())
                manifest["bundle_sha256"] = "0" * 64
                path.write_text(json.dumps(manifest))
                with self.assertRaises(ValueError):
                    bundle.run("verify", "a" * 40, "out")
            finally:
                os.chdir(previous)

    def test_wrong_target_and_lock_rejected(self):
        with tempfile.TemporaryDirectory(prefix="edaratek-review-") as root:
            previous = Path.cwd()
            try:
                os.chdir(root)
                Path("composer.lock").write_bytes(b"fixture lock")
                raw = archive("composer.lock", b"fixture lock")
                with patch.object(bundle.subprocess, "check_output", side_effect=["a" * 40, b"", raw]):
                    bundle.run("create", "a" * 40, "out")
                path = Path("out/release-manifest.json")
                original = json.loads(path.read_text())
                for key, value in (("commit", "b" * 40), ("target", "production.invalid"), ("migrations_authorized", True), ("deployment_executed", True)):
                    changed = dict(original)
                    changed[key] = value
                    path.write_text(json.dumps(changed))
                    with self.subTest(key=key), self.assertRaises(ValueError):
                        bundle.run("verify", "a" * 40, "out")
                path.write_text(json.dumps(original))
                Path("composer.lock").write_bytes(b"different lock")
                with self.assertRaises(ValueError):
                    bundle.run("verify", "a" * 40, "out")
            finally:
                os.chdir(previous)


if __name__ == "__main__":
    unittest.main()
