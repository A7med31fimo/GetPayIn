// k6-tests/concurrency-stress-test.js

import http from 'k6/http';
import { check } from 'k6';
import { Rate, Counter } from 'k6/metrics';

// Custom metrics
const successfulHolds = new Counter('successful_holds');
const failedHolds = new Counter('failed_holds');
const oversellDetected = new Rate('oversell_detected');

const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:8000';
const PRODUCT_ID = 1;

export const options = {
  scenarios: {
    // Scenario 1: Burst load - simulate flash sale start
    burst_load: {
      executor: 'constant-arrival-rate',
      rate: 200,              // 200 requests per second
      timeUnit: '1s',
      duration: '10s',        // For 10 seconds
      preAllocatedVUs: 50,
      maxVUs: 200,
    },
  },
};

export function setup() {
  const res = http.get(`${BASE_URL}/api/products/${PRODUCT_ID}`);
  const product = JSON.parse(res.body);
  
  console.log(`\n=== Test Configuration ===`);
  console.log(`Initial Available Stock: ${product.available_stock}`);
  console.log(`Expected: At most ${product.available_stock} successful holds`);
  console.log(`Starting burst test...\n`);
  
  return {
    initialStock: product.available_stock,
  };
}

export default function () {
  const headers = { 'Content-Type': 'application/json' };
  
  // Try to create a hold for 1 item
  const holdPayload = JSON.stringify({
    product_id: PRODUCT_ID,
    qty: 1,
  });
  
  const holdRes = http.post(
    `${BASE_URL}/api/holds`,
    holdPayload,
    { headers }
  );
  
  if (holdRes.status === 201) {
    successfulHolds.add(1);
    
    check(holdRes, {
      'hold created successfully': (r) => r.status === 201,
      'hold has valid data': (r) => {
        const body = JSON.parse(r.body);
        return body.hold_id && body.expires_at;
      },
    });
  } else if (holdRes.status === 400) {
    failedHolds.add(1);
    
    const body = JSON.parse(holdRes.body);
    check(holdRes, {
      'hold failed with proper error': (r) => 
        body.error && body.error.includes('Insufficient stock'),
    });
  } else {
    failedHolds.add(1);
  }
}

export function teardown(data) {
  const res = http.get(`${BASE_URL}/api/products/${PRODUCT_ID}`);
  const product = JSON.parse(res.body);
  
  console.log('\n=== Concurrency Test Results ===');
  console.log(`Initial Stock: ${data.initialStock}`);
  console.log(`Final Available Stock: ${product.available_stock}`);
  console.log(`Reserved Stock: ${product.total_stock - product.available_stock}`);
  
  // Calculate from database
  const expectedReserved = data.initialStock - product.available_stock;
  
  console.log(`\nExpected Max Holds: ${data.initialStock}`);
  console.log(`Reserved in DB: ${expectedReserved}`);
  
  // Check for overselling
  if (product.available_stock < 0) {
    console.error('\n❌ CRITICAL: Available stock is NEGATIVE!');
    console.error('This indicates overselling occurred!');
    oversellDetected.add(1);
  } else if (expectedReserved > data.initialStock) {
    console.error('\n❌ CRITICAL: More items reserved than initial stock!');
    console.error('This indicates overselling occurred!');
    oversellDetected.add(1);
  } else {
    console.log('\n✅ SUCCESS: No overselling detected!');
    console.log('Stock consistency maintained under high concurrency.');
    oversellDetected.add(0);
  }
}