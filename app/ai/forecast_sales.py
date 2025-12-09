import mysql.connector
import pandas as pd
from sklearn.linear_model import LinearRegression
import json
import datetime
import argparse
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

# --- Argument Parsing ---
parser = argparse.ArgumentParser(description='Sales Forecasting Script')
parser.add_argument('--days', type=int, default=7, help='Number of days to forecast from the last data point.')
parser.add_argument('--start_date', type=str, help='The start date for the forecast period (YYYY-MM-DD).')
parser.add_argument('--end_date', type=str, help='The end date for the forecast period (YYYY-MM-DD).')
args = parser.parse_args()


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
forecast_days = args.days

current_date = pd.to_datetime(df['sale_date'].max())

# --- Determine Forecast Range ---
# Generate a forecast for a reasonable maximum period (e.g., 90 days)
max_forecast_days = 90
for i in range(1, max_forecast_days + 1):
    next_date = current_date + datetime.timedelta(days=i)
    next_index = last_day_index + i
    prediction = max(0, model.predict([[next_index]])[0])
    
    # We only need to store if it falls within the requested range
    
    # Case 1: Custom Date Range is provided
    if args.start_date and args.end_date:
        start_dt = datetime.datetime.strptime(args.start_date, '%Y-%m-%d').date()
        end_dt = datetime.datetime.strptime(args.end_date, '%Y-%m-%d').date()
        if start_dt <= next_date.date() <= end_dt:
            predicted_sales.append(round(prediction, 2))
            future_dates.append(next_date.strftime('%Y-%m-%d'))
            
    # Case 2: No custom range, use 'days' argument
    else:
        if i <= args.days:
            predicted_sales.append(round(prediction, 2))
            future_dates.append(next_date.strftime('%Y-%m-%d'))
        else:
            # We have forecasted enough days
            break

# 6. Output JSON for PHP
result = {
    "history_dates": df['sale_date'].astype(str).tolist(),
    "history_sales": df['total'].tolist(),
    "forecast_dates": future_dates,
    "forecast_sales": predicted_sales
}

print(json.dumps(result))