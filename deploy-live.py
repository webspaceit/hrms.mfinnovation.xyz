"""Update the HRMS checkout on the live shared host.

This file is sent to the host over SSH by push-project.bat and runs there.
"""

from pathlib import Path
import shutil
import subprocess
import tempfile
import sys


ROOT = Path("/home/sites/27b/0/0b72e9e422/hrms.mfinnovation.xyz")
BRANCH = "main"


def run(args):
    print("RUN:", " ".join(args), flush=True)
    subprocess.run(args, cwd=str(ROOT), check=True)


if not ROOT.is_dir() or not (ROOT / ".git").is_dir():
    sys.exit(f"HRMS Git checkout not found at expected path: {ROOT}")

config = ROOT / "config" / "config.php"
backup = None
if config.exists():
    with tempfile.NamedTemporaryFile(prefix="hrms-config-") as tmp:
        backup = Path(tmp.name)
        shutil.copy2(config, backup)
        run(["git", "fetch", "origin", BRANCH])
        run(["git", "reset", "--hard", f"origin/{BRANCH}"])
        config.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(backup, config)
else:
    run(["git", "fetch", "origin", BRANCH])
    run(["git", "reset", "--hard", f"origin/{BRANCH}"])

run(["git", "--no-pager", "log", "--oneline", "-1"])
print("DEPLOY_OK", flush=True)
