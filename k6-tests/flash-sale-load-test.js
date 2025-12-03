// k6-tests/flash-sale-load-test.js

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';

// Custom metrics
const errorRate = new Rate('errors');
const oversellRate = new Rate('oversells');

// Configuration
const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
const PRODUCT_ID = 1;
const INITIAL_STOCK = 100; // Match your seeded stock

export const options = {
  stages: [
    { duration: '10s', target: 50 },   // Ramp up to 50 users
    { duration: '30s', target: 100 },  // Spike to 100 users
    { duration: '20s', target: 50 },   // Ramp down
    { duration: '10s', target: 0 },    // Cool down
  ],
  thresholds: {
    http_req_duration: ['p(95)<500'], // 95% of requests should be below 500ms
    http_req_failed: ['rate<0.1'],    // Less than 10% failure rate
    errors: ['rate<0.1'],
  },
};

// Setup: Check initial stock
export function setup() {
  const res = http.get(`${BASE_URL}/api/products/${PRODUCT_ID}`);
  const product = JSON.parse(res.body);
  
  console.log(`Initial Stock: ${product.available_stock}`);
  
  return {
    initialStock: product.available_stock,
  };
}

// Main test scenario
export default function (data) {
  const scenario = Math.random();
  
  // 70% - Complete purchase flow
  if (scenario < 0.7) {
    testCompletePurchase();
  }
  // 20% - Just check product
  else if (scenario < 0.9) {
    testGetProduct();
  }
  // 10% - Create hold but don't complete
  else {
    testAbandonedHold();
  }
  
  sleep(1);
}

// Test 1: Complete purchase flow
function testCompletePurchase() {
  const headers = { 'Content-Type': 'application/json' };
  
  // Step 1: Create hold
  const holdPayload = JSON.stringify({
    product_id: PRODUCT_ID,
    qty: Math.floor(Math.random() * 3) + 1, // 1-3 items
  });
  
  const holdRes = http.post(
    `${BASE_URL}/api/holds`,
    holdPayload,
    { headers }
  );
  
  const holdSuccess = check(holdRes, {
    'hold created': (r) => r.status === 201 || r.status === 400,
    'hold has valid response': (r) => {
      if (r.status === 201) {
        const body = JSON.parse(r.body);
        return body.hold_id && body.expires_at;
      }
      return true;
    },
  });
  
  if (!holdSuccess || holdRes.status !== 201) {
    errorRate.add(1);
    return;
  }
  
  const holdData = JSON.parse(holdRes.body);
  
  // Step 2: Create order
  const orderPayload = JSON.stringify({
    hold_id: holdData.hold_id,
  });
  
  const orderRes = http.post(
    `${BASE_URL}/api/orders`,
    orderPayload,
    { headers }
  );
  
  const orderSuccess = check(orderRes, {
    'order created': (r) => r.status === 201,
    'order has valid response': (r) => {
      if (r.status === 201) {
        const body = JSON.parse(r.body);
        return body.order_id && body.status === 'pending';
      }
      return false;
    },
  });
  
  if (!orderSuccess) {
    errorRate.add(1);
    return;
  }
  
  const orderData = JSON.parse(orderRes.body);
  
  // Step 3: Send payment webhook
  const webhookPayload = JSON.stringify({
    idempotency_key: `load-test-${Date.now()}-${Math.random()}`,
    order_id: orderData.order_id,
    status: Math.random() > 0.1 ? 'success' : 'failure', // 90% success
    payload: {
      transaction_id: `txn_${Date.now()}`,
      amount: orderData.total_price,
    },
  });
  
  const webhookRes = http.post(
    `${BASE_URL}/api/payments/webhook`,
    webhookPayload,
    { headers }
  );
  
  check(webhookRes, {
    'webhook processed': (r) => r.status === 200,
    'webhook has valid response': (r) => {
      if (r.status === 200) {
        const body = JSON.parse(r.body);
        return body.success && body.order_status;
      }
      return false;
    },
  });
}

// Test 2: Just get product info
function testGetProduct() {
  const res = http.get(`${BASE_URL}/api/products/${PRODUCT_ID}`);
  
  check(res, {
    'product fetched': (r) => r.status === 200,
    'product has stock info': (r) => {
      const body = JSON.parse(r.body);
      return body.available_stock !== undefined;
    },
  });
}

// Test 3: Abandoned hold (simulates users who don't complete)
function testAbandonedHold() {
  const headers = { 'Content-Type': 'application/json' };
  
  const holdPayload = JSON.stringify({
    product_id: PRODUCT_ID,
    qty: 1,
  });
  
  const holdRes = http.post(
    `${BASE_URL}/api/holds`,
    holdPayload,
    { headers }
  );
  
  check(holdRes, {
    'hold created for abandonment': (r) => r.status === 201 || r.status === 400,
  });
  
  // Don't complete the order - simulates abandonment
}

// Teardown: Verify no overselling
export function teardown(data) {
  const res = http.get(`${BASE_URL}/api/products/${PRODUCT_ID}`);
  const product = JSON.parse(res.body);
  
  console.log('\n=== Final Results ===');
  console.log(`Initial Stock: ${data.initialStock}`);
  console.log(`Final Total Stock: ${product.total_stock}`);
  console.log(`Final Available Stock: ${product.available_stock}`);
  console.log(`Reserved Stock: ${product.total_stock - product.available_stock}`);
  
  const soldItems = data.initialStock - product.total_stock;
  console.log(`Items Sold: ${soldItems}`);
  
  // Check for overselling
  if (product.available_stock < 0) {
    console.error('❌ OVERSELLING DETECTED! Available stock is negative!');
    oversellRate.add(1);
  } else if (soldItems > data.initialStock) {
    console.error('❌ OVERSELLING DETECTED! More items sold than available!');
    oversellRate.add(1);
  } else {
    console.log('✅ No overselling detected');
    oversellRate.add(0);
  }
}