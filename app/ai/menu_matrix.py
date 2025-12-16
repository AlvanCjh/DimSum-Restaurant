import mysql.connector
import pandas as pd
import json
import sys

# 1. Connect to Database
try:
    conn = mysql.connector.connect(
        host='localhost', user='root', password='', database='yobita_db'
    )
except Exception as e:
    print(json.dumps({"error": str(e)}))
    sys.exit()

# 2. Fetch Sales Data (Last 30 Days)
# We aggregate quantity sold (Popularity) and calculated revenue per item
query = """
    SELECT 
        mi.id,
        mi.name, 
        mi.price,
        COALESCE(SUM(oi.quantity), 0) as total_qty
    FROM menu_items mi
    LEFT JOIN order_items oi ON mi.id = oi.menu_item_id
    LEFT JOIN orders o ON oi.order_id = o.id
    WHERE o.status = 'completed' 
      AND DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY mi.id, mi.name, mi.price
    HAVING total_qty > 0
"""

df = pd.read_sql(query, conn)
conn.close()

if df.empty:
    print(json.dumps({"error": "Not enough sales data."}))
    sys.exit()

# 3. Calculate Metrics (Using Estimated 35% Food Cost)
ESTIMATED_COST_PCT = 0.35

df['price'] = df['price'].astype(float)
df['cost'] = df['price'] * ESTIMATED_COST_PCT
df['margin'] = df['price'] - df['cost']
df['total_profit'] = df['margin'] * df['total_qty']

# 4. Determine Quadrants
# A. Popularity Threshold (70% Rule)
avg_share_benchmark = (1 / len(df)) * 0.7 
total_sales_all = df['total_qty'].sum()
df['sales_share'] = df['total_qty'] / total_sales_all

# B. Profitability Threshold (Average Weighted Margin)
total_margin_all = df['total_profit'].sum()
avg_margin_benchmark = total_margin_all / total_sales_all

def categorize(row):
    high_pop = row['sales_share'] >= avg_share_benchmark
    high_profit = row['margin'] >= avg_margin_benchmark
    
    if high_pop and high_profit:
        return "Top Performers"       
    elif high_pop and not high_profit:
        return "Popular Staples"  
    elif not high_pop and high_profit:
        return "High Potential"     
    else:
        return "Underperformers"        

df['category'] = df.apply(categorize, axis=1)

# 5. Output JSON
result = {
    "benchmark_x": float(avg_share_benchmark * total_sales_all), # Qty cutoff
    "benchmark_y": float(avg_margin_benchmark), # Margin cutoff
    "items": df.to_dict(orient='records')
}

print(json.dumps(result))