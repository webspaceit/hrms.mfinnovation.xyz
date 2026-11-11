"""Update the HRMS checkout on the live shared host.

This file is sent to the host over SSH by push-project.bat and runs there.
"""

from pathlib import Path
import shutil
import subprocess
import tempfile
import sys


REPOSITORY = "hrms.mfinnovation.xyz.git"
BRANCH = "main"


def run(args, cwd):
    print("RUN:", " ".join(args), flush=True)
    subprocess.run(args, cwd=str(cwd), check=True)


def find_checkout():
    sites = Path("/home/sites")
    for account_root in sites.glob("*/*/*"):
        if not account_root.is_dir():
            continue
        candidates = [account_root]
        try:
            candidates.extend(p for p in account_root.iterdir() if p.is_dir())
        except OSError:
            continue
        for candidate in candidates:
            if not (candidate / ".git").exists():
                continue
            try:
                remote = subprocess.run(
                    ["git", "-C", str(candidate), "remote", "get-url", "origin"],
                    check=True, capture_output=True, text=True,
                ).stdout.strip().lower()
            except subprocess.CalledProcessError:
                continue
            if remote.endswith("/" + REPOSITORY) or remote.endswith(":" + REPOSITORY):
                if (candidate / "app" / "routes.php").is_file() and (candidate / "classes" / "Payment.php").is_file():
                    return candidate
    return None


ROOT = find_checkout()
if ROOT is None:
    sys.exit(
        "HRMS checkout not found under /home/sites. Expected a Git checkout "
        "whose origin ends with /hrms.mfinnovation.xyz.git and contains "
        "app/routes.php plus classes/Payment.php."
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
