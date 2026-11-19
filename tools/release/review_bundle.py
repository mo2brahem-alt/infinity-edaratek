"""Immutable test source review bundle. No deployment or Git writes."""
import argparse
import gzip
import hashlib
import io
import json
import re
import subprocess
import tarfile
from pathlib import Path, PurePosixPath


def check_sha(value):
    if not re.fullmatch(r"[0-9a-f]{40}", value):
        raise ValueError("A full commit SHA is required")


def check_members(data):
    with tarfile.open(fileobj=io.BytesIO(data), mode="r:") as archive:
        for entry in archive:
            path = PurePosixPath(entry.name)
            name = path.name.lower()
            if entry.issym() or entry.islnk() or entry.isdev() or path.is_absolute() or ".." in path.parts or "\\" in entry.name:
                raise ValueError("Unsafe archive member")
            if (name == ".env" or (name.startswith(".env.") and name != ".env.example")
                or name in ("auth.json", ".npmrc", "local.properties", "key.properties")
                or path.suffix.lower() in (".key", ".pem", ".jks", ".keystore", ".p8", ".p12", ".pfx")
                or re.match(r"(?:credentials|service-account).*\.json$", name)):
                raise ValueError("Sensitive archive path")
            if entry.isfile():
                content = archive.extractfile(entry).read()
                if (re.search(rb"-----BEGIN (?:RSA |EC |OPENSSH |ENCRYPTED )?PRIVATE KEY-----", content)
                    or re.search(rb'"type"\s*:\s*"service_account"', content)
                    or re.search(rb"\b(?:gh[pousr]_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{40,}|AKIA[A-Z0-9]{16}|eyJ[A-Za-z0-9_-]{16,}\.[A-Za-z0-9_-]{16,}\.[A-Za-z0-9_-]{16,})\b", content)):
                    raise ValueError("Credential pattern in source bundle")


def run(mode, sha, directory):
    check_sha(sha)
    directory = Path(directory)
    bundle = directory / "edaratek-test-source.tar.gz"
    manifest_path = directory / "release-manifest.json"
    if mode == "create":
        head = subprocess.check_output(["git", "rev-parse", "HEAD"], text=True).strip()
        if head != sha or subprocess.check_output(["git", "status", "--porcelain", "--untracked-files=all"]):
            raise ValueError("Bundle requires a clean checkout of the exact approved commit")
        raw = subprocess.check_output(["git", "archive", "--format=tar", sha])
        check_members(raw)
        directory.mkdir(parents=True, exist_ok=False)
        bundle.write_bytes(gzip.compress(raw, mtime=0))
        manifest_path.write_text(json.dumps({
            "commit": sha, "target": "test.edaratek.com", "kind": "source_review_only",
            "bundle_sha256": hashlib.sha256(bundle.read_bytes()).hexdigest(),
            "composer_lock_sha256": hashlib.sha256(Path("composer.lock").read_bytes()).hexdigest(),
            "migrations_authorized": False, "deployment_executed": False,
        }, indent=2) + "\n", encoding="utf-8")
    else:
        manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
        if (manifest.get("commit") != sha or manifest.get("target") != "test.edaratek.com"
            or manifest.get("kind") != "source_review_only" or manifest.get("migrations_authorized") is not False
            or manifest.get("deployment_executed") is not False
            or manifest.get("bundle_sha256") != hashlib.sha256(bundle.read_bytes()).hexdigest()
            or manifest.get("composer_lock_sha256") != hashlib.sha256(Path("composer.lock").read_bytes()).hexdigest()):
            raise ValueError("Artifact identity mismatch")
        raw = gzip.decompress(bundle.read_bytes())
        if raw != subprocess.check_output(["git", "archive", "--format=tar", sha]):
            raise ValueError("Bundle differs from the approved Git commit")
        check_members(raw)
        with tarfile.open(fileobj=io.BytesIO(raw), mode="r:") as archive:
            if hashlib.sha256(archive.extractfile("composer.lock").read()).hexdigest() != manifest["composer_lock_sha256"]:
                raise ValueError("Bundled lock mismatch")
    print("TEST_SOURCE_BUNDLE_VERIFIED")


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("mode", choices=("create", "verify"))
    parser.add_argument("--sha", required=True)
    parser.add_argument("--directory", required=True)
    args = parser.parse_args()
    try:
        run(args.mode, args.sha, args.directory)
    except Exception:
        raise SystemExit("SOURCE_BUNDLE_REJECTED") from None
