import json
from datetime import datetime, timezone
from typing import Dict, List, Any
import sys


def parse_dt(value: str):
    if not value:
        return None
    try:
        return datetime.fromisoformat(value)
    except Exception:
        # Try common mysql format
        try:
            return datetime.strptime(value, "%Y-%m-%d %H:%M:%S")
        except Exception:
            return None


def evaluate_badge(member: Dict[str, Any], max_orders: int):
    orders = int(member.get("orders_handled") or 0)

    # Rule 1: Less than 10 orders => Needs Training
    if orders < 10:
        return "🧭 Needs Training", "Fewer than 10 orders handled"

    # Rule 2: Most orders => Star badge
    if max_orders > 0 and orders == max_orders:
        return "⭐ Star Performer", "Highest order count in the team"

    # Rule 3: More than or equal to 10 orders => Consistent
    if orders >= 10:
        return "✅ Consistent", "Meets expected throughput"

    return "🟢 Consistent", "Steady performance"


def main():
    try:
        payload = json.load(sys.stdin)
        staff = payload.get("staff", [])
    except Exception:
        print(json.dumps({"badges": []}))
        return

    orders_list = [int(s.get("orders_handled") or 0) for s in staff]
    max_orders = max(orders_list) if orders_list else 0

    badges: List[Dict[str, Any]] = []
    for member in staff:
        label, reason = evaluate_badge(member, max_orders)
        badges.append(
            {"id": member.get("id"), "label": label, "reason": reason}
        )

    print(json.dumps({"badges": badges}))


if __name__ == "__main__":
    main()