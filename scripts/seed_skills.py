"""Örnek meslek ve yetenek verisini yükler (geliştirme ortamı)."""

import json
from pathlib import Path

DATA_DIR = Path(__file__).resolve().parent.parent / "data" / "roles"


def load_roles() -> list[dict]:
    path = DATA_DIR / "bootcamp_roles.json"
    with open(path, encoding="utf-8") as f:
        return json.load(f)["roles"]


if __name__ == "__main__":
    roles = load_roles()
    print(f"Yüklendi: {len(roles)} meslek")
    for role in roles:
        skills = len(role["required_skills"])
        print(f"  - {role['title']}: {skills} yetenek, {role['weeks_template']} hafta")
