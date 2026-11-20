import re
import sqlite3
from pathlib import Path

root = Path(__file__).resolve().parents[1]
source = (root / "admin" / "pages" / "trade_requests.php").read_text(encoding="utf-8")
match = re.search(
    r"ORDER BY\s+(CASE\s+tr\.status\b.*?tr\.id\s+DESC)\s*\n\s*LIMIT",
    source,
    re.IGNORECASE | re.DOTALL,
)
assert match, "Admin trade request query must declare status-prioritized ordering."

connection = sqlite3.connect(":memory:")
connection.execute(
    "CREATE TABLE trade_requests (id INTEGER PRIMARY KEY, status TEXT, requested_at TEXT)"
)
connection.executemany(
    "INSERT INTO trade_requests (id, status, requested_at) VALUES (?, ?, ?)",
    [
        (1, "REJECTED", "2026-11-20 00:47:00"),
        (2, "PENDING", "2026-11-19 20:00:00"),
        (3, "PENDING", "2026-11-20 00:30:00"),
        (4, "APPROVED", "2026-11-20 00:45:00"),
        (5, "COMPLETED", "2026-11-20 00:46:00"),
        (6, "CANCELLED", "2026-11-20 00:45:00"),
        (7, "REJECTED", "2026-11-20 00:45:00"),
    ],
)
ordered_ids = [
    row[0]
    for row in connection.execute(
        "SELECT tr.id FROM trade_requests tr ORDER BY " + match.group(1)
    )
]
assert ordered_ids == [3, 2, 4, 1, 5, 7, 6], ordered_ids
print("PASS pending first; newest pending first; approved next; resolved requests after")
