import mysql.connector
import pandas as pd
import json
import sys
import datetime

# 1. Get arguments from PHP (daily or weekly)
timeframe = sys.argv[1] if len(sys.argv) > 1 else 'daily'

# 2. Connect to DB
try:
    conn = mysql.connector.connect(
        host='localhost', user='root', password='', database='yobita_db'
    )
except Exception as e:
    print(json.dumps({"error": str(e)}))
    sys.exit()

# 3. Determine Date Range
today = datetime.date.today()
if timeframe == 'weekly':
    start_date = today - datetime.timedelta(days=7)
    previous_start = start_date - datetime.timedelta(days=7)
    date_label = "Last 7 Days"
else:
    start_date = today
    previous_start = today - datetime.timedelta(days=1)
    date_label = "Today"

# 4. Fetch Current vs Previous Data
def get_stats(start_d, end_d):
    query = f"""
        SELECT 
            COUNT(id) as total_orders,
            COALESCE(SUM(total_amount), 0) as revenue
        FROM orders 
        WHERE DATE(created_at) >= '{start_d}' AND DATE(created_at) < '{end_d}'
        AND status = 'completed'
    """
    df = pd.read_sql(query, conn)
    return df.iloc[0]

# Current Period
current = get_stats(start_date, today + datetime.timedelta(days=1))
# Previous Period (for comparison)
previous = get_stats(previous_start, start_date)

# 5. Fetch Top Selling Item
top_item_query = f"""
    SELECT mi.name, SUM(oi.quantity) as qty
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN menu_items mi ON oi.menu_item_id = mi.id
    WHERE DATE(o.created_at) >= '{start_date}' AND o.status = 'completed'
    GROUP BY mi.name
    ORDER BY qty DESC LIMIT 1
"""
top_item_df = pd.read_sql(top_item_query, conn)
top_item = top_item_df.iloc[0]['name'] if not top_item_df.empty else "N/A"

conn.close()

# 6. Generate the AI Narrative
revenue_diff = current['revenue'] - previous['revenue']
growth_pct = 0
if previous['revenue'] > 0:
    growth_pct = (revenue_diff / previous['revenue']) * 100

summary = ""
sentiment = "neutral"

if current['revenue'] == 0:
    summary = f"No sales recorded for {date_label.lower()} yet."
    sentiment = "neutral"
else:
    # Trend Analysis
    if revenue_diff > 0:
        summary += f"🚀 **Great news!** Revenue is **up {round(growth_pct, 1)}%** compared to the previous period. "
        sentiment = "positive"
    elif revenue_diff < 0:
        summary += f"📉 Revenue is **down {abs(round(growth_pct, 1))}%** compared to the previous period. "
        sentiment = "negative"
    else:
        summary += f"⚖️ Revenue is stable compared to the previous period. "

    # Top Item Insight
    if top_item != "N/A":
        summary += f"The most popular item was **{top_item}**, contributing significantly to the volume."

# 7. Output JSON
result = {
    "revenue": float(current['revenue']),
    "previous_revenue": float(previous['revenue']), 
    "orders": int(current['total_orders']),
    "summary": summary,
    "sentiment": sentiment,
    "top_item": top_item
}

print(json.dumps(result))