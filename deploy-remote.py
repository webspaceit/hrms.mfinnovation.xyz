"""Safely unpack the deployment ZIP into the HRMS web root."""
from pathlib import Path
import shutil
import stat
import sys
import zipfile


ROOT = Path("/home/sites/27b/0/0b72e9e422/hrms.mfinnovation.xyz").resolve()
archive_path = Path(sys.argv[1]).resolve()
if archive_path.parent != Path("/tmp") or not archive_path.name.startswith("hrms-deploy-"):
    raise SystemExit("Refusing an unexpected archive path.")
if not ROOT.is_dir() or not (ROOT / "index.php").is_file():
    raise SystemExit(f"HRMS web root is missing or invalid: {ROOT}")

count = 0
try:
    with zipfile.ZipFile(archive_path, "r") as archive:
        for item in archive.infolist():
            name = item.filename.replace("\\", "/")
            relative = Path(name)
            target = (ROOT / relative).resolve()
            if relative.is_absolute() or ".." in relative.parts or (target != ROOT and ROOT not in target.parents):
                raise SystemExit(f"Unsafe archive path: {item.filename}")
            mode = item.external_attr >> 16
            if stat.S_ISLNK(mode):
                raise SystemExit(f"Refusing symlink in archive: {item.filename}")

        for item in archive.infolist():
            target = ROOT / Path(item.filename.replace("\\", "/"))
            if item.is_dir():
                target.mkdir(parents=True, exist_ok=True)
                continue
            target.parent.mkdir(parents=True, exist_ok=True)
            with archive.open(item, "r") as source, target.open("wb") as destination:
                shutil.copyfileobj(source, destination)
            count += 1
finally:
    try:
        archive_path.unlink()
    except FileNotFoundError:
        pass

print(f"DEPLOY_OK: unpacked {count} files into {ROOT}", flush=True)
