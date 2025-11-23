import mysql.connector
import pandas as pd
from sklearn.linear_model import LinearRegression
import json
import datetime
import sys

# 1. Connect to Database
try:
    conn = mysql.connector.connect(
        host='localhost',
        user='root',      # Default XAMPP user
        password='',      # Default XAMPP password
        database='yobita_db'
    )
except Exception as e:
    print(json.dumps({"error": str(e)}))
    sys.exit()

# 2. Fetch Daily Sales Data (Only Completed Orders)
query = """
    SELECT DATE(created_at) as sale_date, SUM(total_amount) as total 
    FROM orders 
    WHERE status = 'completed' 
    GROUP BY DATE(created_at) 
    ORDER BY sale_date ASC
"""

df = pd.read_sql(query, conn)
conn.close()

# Check if we have enough data
if len(df) < 3:
    print(json.dumps({"error": "Not enough data to forecast (need at least 3 days)."}))
    sys.exit()

# 3. Prepare Data for AI
# Linear Regression needs numbers, not dates. We convert dates to "Day Number" (0, 1, 2...)
df['day_index'] = range(len(df)) 

X = df[['day_index']]  # Input: Day Number
y = df['total']        # Target: Sales Amount

# 4. Train the Model
model = LinearRegression()
model.fit(X, y)

# 5. Predict Next 7 Days
last_day_index = df['day_index'].max()
future_days = []
predicted_sales = []
future_dates = []

current_date = pd.to_datetime(df['sale_date'].max())

for i in range(1, 8): # 7 Days
    next_index = last_day_index + i
    prediction = model.predict([[next_index]])[0]
    
    # Don't predict negative sales
    prediction = max(0, prediction)
    
    future_days.append(next_index)
    predicted_sales.append(round(prediction, 2))
    
    # Calculate the actual date string for the chart
    next_date = current_date + datetime.timedelta(days=i)
    future_dates.append(next_date.strftime('%Y-%m-%d'))

# 6. Output JSON for PHP
result = {
    "history_dates": df['sale_date'].astype(str).tolist(),
    "history_sales": df['total'].tolist(),
    "forecast_dates": future_dates,
    "forecast_sales": predicted_sales
}

print(json.dumps(result))