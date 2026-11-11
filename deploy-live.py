"""Update the HRMS checkout on the live shared host.

This file is sent to the host over SSH by push-project.bat and runs there.
"""

from pathlib import Path
import os
import shutil
import subprocess
import tempfile
import sys


REPOSITORY = "hrms.mfinnovation.xyz"
BRANCH = "main"


def run(args, cwd):
    print("RUN:", " ".join(args), flush=True)
    subprocess.run(args, cwd=str(cwd), check=True)


def find_checkout():
    home = Path.home()
    search_roots = [
        Path.cwd(), home, Path("/home/sites"), Path("/home"),
        Path("/var/www"), Path("/var/www/html"), Path("/srv"),
        Path("/var/www/vhosts"),
    ]
    roots = []
    for root in search_roots:
        try:
            resolved = root.resolve()
            if resolved.is_dir() and resolved not in roots:
                roots.append(resolved)
        except OSError:
            pass

    git_candidates = []
    app_candidates = []
    visited = set()
    for search_root in roots:
        for current, dirs, _files in os.walk(search_root):
            candidate = Path(current)
            try:
                resolved = candidate.resolve()
            except OSError:
                dirs[:] = []
                continue
            if resolved in visited:
                dirs[:] = []
                continue
            visited.add(resolved)
            try:
                depth = len(candidate.relative_to(search_root).parts)
            except ValueError:
                depth = 99
            dirs[:] = [
                d for d in dirs
                if d not in {".git", "vendor", "node_modules", "uploads", "logs", ".cache", ".local", ".npm", "tmp"}
            ]
            if depth > 7:
                dirs[:] = []
            if (candidate / "app" / "routes.php").is_file() or (candidate / "classes" / "Payment.php").is_file():
                app_candidates.append(candidate)
            if not (candidate / ".git").exists():
                continue
            if len(git_candidates) < 40:
                git_candidates.append(candidate)
            try:
                remote = subprocess.run(
                    ["git", "-C", str(candidate), "remote", "get-url", "origin"],
                    check=True, capture_output=True, text=True,
                ).stdout.strip().lower()
            except subprocess.CalledProcessError:
                continue
            repo_name = remote.replace("\\", "/").rsplit("/", 1)[-1].rsplit(":", 1)[-1]
            if repo_name.endswith(".git"):
                repo_name = repo_name[:-4]
            if repo_name == REPOSITORY:
                if (candidate / "app" / "routes.php").is_file() and (candidate / "classes" / "Payment.php").is_file():
                    return candidate, git_candidates, app_candidates
    return None, git_candidates, app_candidates


ROOT, GIT_CANDIDATES, APP_CANDIDATES = find_checkout()
if ROOT is None:
    print(f"SSH working directory: {Path.cwd()}", flush=True)
    print(f"SSH home directory: {Path.home()}", flush=True)
    print("Git checkout candidates:", flush=True)
    for candidate in GIT_CANDIDATES:
        remote = subprocess.run(
            ["git", "-C", str(candidate), "remote", "get-url", "origin"],
            capture_output=True, text=True,
        ).stdout.strip()
        repo_name = remote.replace("\\", "/").rsplit("/", 1)[-1].rsplit(":", 1)[-1]
        if repo_name.endswith(".git"):
            repo_name = repo_name[:-4]
        print(f"  {candidate} (origin repository: {repo_name or 'none'})", flush=True)
    print("HRMS app directory candidates:", flush=True)
    for candidate in APP_CANDIDATES[:40]:
        print(f"  {candidate}", flush=True)
    sys.exit(
        "Could not identify the HRMS deployment checkout in the SSH home or common web roots. "
        "No files were changed."
    )
print("HRMS checkout:", ROOT, flush=True)

config = ROOT / "config" / "config.php"
backup = None
if config.exists():
    with tempfile.NamedTemporaryFile(prefix="hrms-config-") as tmp:
        backup = Path(tmp.name)
        shutil.copy2(config, backup)
        run(["git", "fetch", "origin", BRANCH], ROOT)
        run(["git", "reset", "--hard", f"origin/{BRANCH}"], ROOT)
        config.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(backup, config)
else:
    run(["git", "fetch", "origin", BRANCH], ROOT)
    run(["git", "reset", "--hard", f"origin/{BRANCH}"], ROOT)

run(["git", "--no-pager", "log", "--oneline", "-1"], ROOT)
print("DEPLOY_OK", flush=True)
