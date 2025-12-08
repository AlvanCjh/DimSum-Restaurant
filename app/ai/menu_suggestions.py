import mysql.connector
import pandas as pd
import json
import sys
import datetime

# Connect to DB
try:
    conn = mysql.connector.connect(
        host='localhost', user='root', password='', database='yobita_db'
    )
except Exception as e:
    print(json.dumps({"error": str(e), "suggestions": []}))
    sys.exit()

try:
    # Get popular items from last 30 days
    # Calculate popularity based on total quantity ordered
    popular_query = """
        SELECT 
            mi.id,
            mi.name,
            mi.description,
            mi.price,
            mi.image_url,
            mi.category_id,
            mc.name as category_name,
            COALESCE(SUM(oi.quantity), 0) as total_quantity,
            COUNT(DISTINCT oi.order_id) as order_count,
            COALESCE(SUM(oi.quantity * oi.price), 0) as total_revenue
        FROM menu_items mi
        LEFT JOIN menu_categories mc ON mi.category_id = mc.id
        LEFT JOIN order_items oi ON mi.id = oi.menu_item_id
        LEFT JOIN orders o ON oi.order_id = o.id
            AND o.status = 'completed'
            AND DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY mi.id, mi.name, mi.description, mi.price, mi.image_url, mi.category_id, mc.name
        HAVING total_quantity > 0
        ORDER BY total_quantity DESC, order_count DESC, total_revenue DESC
        LIMIT 6
    """
    
    df_popular = pd.read_sql(popular_query, conn)
    
    suggestions = []
    
    if not df_popular.empty:
        # Get trending items (recent orders in last 7 days)
        trending_query = """
            SELECT 
                mi.id,
                mi.name,
                COALESCE(SUM(oi.quantity), 0) as recent_quantity
            FROM menu_items mi
            LEFT JOIN order_items oi ON mi.id = oi.menu_item_id
            LEFT JOIN orders o ON oi.order_id = o.id
                AND o.status = 'completed'
                AND DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY mi.id, mi.name
            HAVING recent_quantity > 0
            ORDER BY recent_quantity DESC
            LIMIT 3
        """
        
        df_trending = pd.read_sql(trending_query, conn)
        trending_ids = set(df_trending['id'].tolist()) if not df_trending.empty else set()
        
        for idx, row in df_popular.iterrows():
            item = {
                "id": int(row['id']),
                "name": row['name'],
                "description": row['description'] if pd.notna(row['description']) else "",
                "price": float(row['price']),
                "image_url": row['image_url'] if pd.notna(row['image_url']) else "",
                "category": row['category_name'] if pd.notna(row['category_name']) else "Uncategorized",
                "total_quantity": int(row['total_quantity']),
                "order_count": int(row['order_count']),
                "total_revenue": float(row['total_revenue']),
                "badge": "🔥 Trending" if row['id'] in trending_ids else "⭐ Popular"
            }
            suggestions.append(item)
    else:
        # If no order data, return all items (new restaurant scenario)
        all_items_query = """
            SELECT 
                mi.id,
                mi.name,
                mi.description,
                mi.price,
                mi.image_url,
                mi.category_id,
                mc.name as category_name
            FROM menu_items mi
            LEFT JOIN menu_categories mc ON mi.category_id = mc.id
            ORDER BY mi.id DESC
            LIMIT 6
        """
        df_all = pd.read_sql(all_items_query, conn)
        
        for idx, row in df_all.iterrows():
            item = {
                "id": int(row['id']),
                "name": row['name'],
                "description": row['description'] if pd.notna(row['description']) else "",
                "price": float(row['price']),
                "image_url": row['image_url'] if pd.notna(row['image_url']) else "",
                "category": row['category_name'] if pd.notna(row['category_name']) else "Uncategorized",
                "total_quantity": 0,
                "order_count": 0,
                "total_revenue": 0.0,
                "badge": "✨ New"
            }
            suggestions.append(item)
    
    result = {
        "suggestions": suggestions,
        "count": len(suggestions)
    }
    
    print(json.dumps(result))
    
except Exception as e:
    print(json.dumps({"error": str(e), "suggestions": []}))
finally:
    conn.close()

