<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Flash Sale API Docs</title>

    <!-- Swagger UI CSS Style -->
    <style>
        body {
            font-family: "Open Sans", Arial, sans-serif;
            margin: 0;
            background: #fafafa;
        }

        .swagger-container {
            display: flex;
        }

        /* SIDEBAR */
        .sidebar {
            width: 280px;
            background: #1b1b1d;
            color: #fff;
            height: 100vh;
            padding-top: 20px;
            position: fixed;
            overflow-y: auto;
        }

        .sidebar h2 {
            padding-left: 20px;
            font-size: 20px;
            margin-bottom: 10px;
        }

        .sidebar a {
            display: block;
            padding: 10px 20px;
            color: #dcdcdc;
            text-decoration: none;
            font-size: 14px;
            border-left: 3px solid transparent;
        }

        .sidebar a:hover {
            background: #333;
            border-left: 3px solid #61affe;
            color: #fff;
        }

        /* CONTENT */
        .content {
            margin-left: 300px;
            padding: 20px 40px;
            flex: 1;
        }

        h1 {
            font-size: 32px;
            color: #383838;
        }

        .endpoint-box {
            background: white;
            border-radius: 6px;
            margin-top: 20px;
            padding: 15px;
            border-left: 6px solid #61affe;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        .method {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            color: white;
        }

        .GET {
            background: #61affe;
        }

        .POST {
            background: #49cc90;
        }

        .endpoint-url {
            font-size: 16px;
            font-weight: bold;
            margin-left: 10px;
        }

        .summary {
            color: #666;
            margin: 10px 0;
        }

        pre {
            background: #41444b;
            color: #f5f6fa;
            padding: 15px;
            border-radius: 8px;
            font-size: 14px;
            overflow-x: auto;
        }

        .copy-btn {
            background: #2d3436;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            float: right;
            font-size: 12px;
        }

        .copy-btn:hover {
            background: black;
        }
    </style>

    <!-- Highlight.js -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            hljs.highlightAll();

            document.querySelectorAll('.copy-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const code = btn.nextElementSibling.innerText;
                    navigator.clipboard.writeText(code);
                    btn.innerText = "Copied!";
                    setTimeout(() => btn.innerText = "Copy", 1000);
                });
            });
        });
    </script>
</head>


<body>

    <div class="swagger-container">

        <!-- ========== SIDEBAR ========== -->
        <div class="sidebar">
            <h2>API Docs</h2>

            <a href="#products">GET Product</a>
            <a href="#holds">Create Hold</a>
            <a href="#orders">Create Order</a>
            <a href="#webhook">Payment Webhook</a>
        </div>


        <!-- ========== CONTENT ========== -->
        <div class="content">

            <h1>Flash Sale API</h1>
            <p style="color:#555;">This API handles high-concurrency flash sale operations with holds, orders, and payment webhooks.</p>


            <!-- ======================= PRODUCT ======================= -->
            <div id="products" class="endpoint-box">
                <span class="method GET">GET</span>
                <span class="endpoint-url">/api/products/{id}</span>

                <p class="summary">Retrieve a product with stock & reserved stock.</p>

                <button class="copy-btn">Copy</button>
                <pre><code class="json">
GET /api/products/1
            </code></pre>

                <pre><code class="json">
{
   "id": 1,
   "name": "Product A",
   "price": 199.99,
   "total_stock": 50,
   "reserved_stock": 10
}
            </code></pre>
            </div>



            <!-- ======================= HOLDS ======================= -->
            <div id="holds" class="endpoint-box">
                <span class="method POST">POST</span>
                <span class="endpoint-url">/api/holds</span>

                <p class="summary">Create a stock hold (short 30-second reservation).</p>

                <button class="copy-btn">Copy</button>
                <pre><code class="bash">
curl -X POST https://example.com/api/holds \
-H "Content-Type: application/json" \
-d '{ "product_id":1, "quantity":2 }'
            </code></pre>

                <pre><code class="json">
{
  "id": 5,
  "product_id": 1,
  "quantity": 2,
  "expires_at": "2025-11-30T12:00:00Z"
}
            </code></pre>
            </div>



            <!-- ======================= ORDERS ======================= -->
            <div id="orders" class="endpoint-box">
                <span class="method POST">POST</span>
                <span class="endpoint-url">/api/orders</span>

                <p class="summary">Create an order using a valid hold.</p>

                <button class="copy-btn">Copy</button>
                <pre><code class="json">
POST /api/orders
            </code></pre>

                <pre><code class="json">
{
  "id": 99,
  "product_id": 1,
  "hold_id": 5,
  "quantity": 2,
  "total_price": 399.98,
  "status": "pending"
}
            </code></pre>
            </div>



            <!-- ======================= WEBHOOK ======================= -->
            <div id="webhook" class="endpoint-box">
                <span class="method POST">POST</span>
                <span class="endpoint-url">/api/payments/webhook</span>

                <p class="summary">Idempotent payment webhook endpoint.</p>

                <button class="copy-btn">Copy</button>
                <pre><code class="json">
POST /api/payments/webhook
            </code></pre>

                <pre><code class="json">
{
  "idempotency_key": "abcd-1234-xyz",
  "order_id": 99,
  "status": "success",
  "payload": { ... }
}
            </code></pre>
            </div>

        </div>
    </div>

</body>

</html>