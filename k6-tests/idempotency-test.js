// k6-tests/idempotency-test.js

import http from 'k6/http';
import { check, group } from 'k6';
import { Counter } from 'k6/metrics';

const idempotencyViolations = new Counter('idempotency_violations');

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
const PRODUCT_ID = 1;

export const options = {
  vus: 1, // Single user to control flow
  iterations: 1,
};

export default function () {
  const headers = { 'Content-Type': 'application/json' };
  
  group('Idempotency Test - Duplicate Webhooks', () => {
    // Step 1: Create hold
    console.log('\n1. Creating hold...');
    const holdRes = http.post(
      `${BASE_URL}/api/holds`,
      JSON.stringify({
        product_id: PRODUCT_ID,
        qty: 2,
      }),
      { headers }
    );
    
    check(holdRes, { 'hold created': (r) => r.status === 201 });
    const holdData = JSON.parse(holdRes.body);
    console.log(`   Hold ID: ${holdData.hold_id}`);
    
    // Step 2: Create order
    console.log('\n2. Creating order...');
    const orderRes = http.post(
      `${BASE_URL}/api/orders`,
      JSON.stringify({
        hold_id: holdData.hold_id,
      }),
      { headers }
    );
    
    check(orderRes, { 'order created': (r) => r.status === 201 });
    const orderData = JSON.parse(orderRes.body);
    console.log(`   Order ID: ${orderData.order_id}`);
    
    // Step 3: Send webhook FIRST time
    const idempotencyKey = `test-${Date.now()}-${Math.random()}`;
    const webhookPayload = JSON.stringify({
      idempotency_key: idempotencyKey,
      order_id: orderData.order_id,
      status: 'success',
      payload: { transaction_id: 'txn_123' },
    });
    
    console.log('\n3. Sending webhook (1st time)...');
    const webhook1 = http.post(
      `${BASE_URL}/api/payments/webhook`,
      webhookPayload,
      { headers }
    );
    
    const webhook1Data = JSON.parse(webhook1.body);
    check(webhook1, {
      'first webhook processed': (r) => r.status === 200,
      'first webhook not already processed': (r) => {
        const body = JSON.parse(r.body);
        return body.already_processed === false;
      },
      'order marked as paid': (r) => {
        const body = JSON.parse(r.body);
        return body.order_status === 'paid';
      },
    });
    
    console.log(`   Already Processed: ${webhook1Data.already_processed}`);
    console.log(`   Order Status: ${webhook1Data.order_status}`);
    console.log(`   Webhook ID: ${webhook1Data.webhook_id}`);
    
    // Get stock after first webhook
    const stockAfterFirst = http.get(`${BASE_URL}/api/products/${PRODUCT_ID}`);
    const stock1 = JSON.parse(stockAfterFirst.body);
    console.log(`   Stock after 1st webhook: ${stock1.total_stock}`);
    
    // Step 4: Send SAME webhook AGAIN (should be idempotent)
    console.log('\n4. Sending SAME webhook (2nd time)...');
    const webhook2 = http.post(
      `${BASE_URL}/api/payments/webhook`,
      webhookPayload, // Same payload!
      { headers }
    );
    
    const webhook2Data = JSON.parse(webhook2.body);
    check(webhook2, {
      'duplicate webhook processed': (r) => r.status === 200,
      'duplicate webhook already processed flag': (r) => {
        const body = JSON.parse(r.body);
        return body.already_processed === true;
      },
      'duplicate webhook same webhook ID': (r) => {
        const body = JSON.parse(r.body);
        return body.webhook_id === webhook1Data.webhook_id;
      },
      'order still paid': (r) => {
        const body = JSON.parse(r.body);
        return body.order_status === 'paid';
      },
    });
    
    console.log(`   Already Processed: ${webhook2Data.already_processed}`);
    console.log(`   Order Status: ${webhook2Data.order_status}`);
    console.log(`   Webhook ID: ${webhook2Data.webhook_id}`);
    
    // Get stock after second webhook
    const stockAfterSecond = http.get(`${BASE_URL}/api/products/${PRODUCT_ID}`);
    const stock2 = JSON.parse(stockAfterSecond.body);
    console.log(`   Stock after 2nd webhook: ${stock2.total_stock}`);
    
    // Step 5: Verify stock didn't change
    if (stock1.total_stock !== stock2.total_stock) {
      console.error('\n❌ IDEMPOTENCY VIOLATION: Stock changed after duplicate webhook!');
      idempotencyViolations.add(1);
    } else {
      console.log('\n✅ SUCCESS: Stock remained consistent after duplicate webhook');
    }
    
    // Step 6: Send webhook a THIRD time (just to be sure)
    console.log('\n5. Sending SAME webhook (3rd time)...');
    const webhook3 = http.post(
      `${BASE_URL}/api/payments/webhook`,
      webhookPayload,
      { headers }
    );
    
    check(webhook3, {
      'third webhook returns same result': (r) => {
        const body = JSON.parse(r.body);
        return body.already_processed === true && 
               body.webhook_id === webhook1Data.webhook_id;
      },
    });
    
    const stockAfterThird = http.get(`${BASE_URL}/api/products/${PRODUCT_ID}`);
    const stock3 = JSON.parse(stockAfterThird.body);
    
    if (stock1.total_stock !== stock3.total_stock) {
      console.error('❌ Stock changed after 3rd duplicate!');
      idempotencyViolations.add(1);
    } else {
      console.log('✅ Stock still consistent after 3rd duplicate');
    }
  });
  
  group('Concurrent Duplicate Webhooks', () => {
    console.log('\n\n=== Testing Concurrent Duplicate Webhooks ===\n');
    
    // Create new order
    const holdRes = http.post(
      `${BASE_URL}/api/holds`,
      JSON.stringify({ product_id: PRODUCT_ID, qty: 1 }),
      { headers }
    );
    const holdData = JSON.parse(holdRes.body);
    
    const orderRes = http.post(
      `${BASE_URL}/api/orders`,
      JSON.stringify({ hold_id: holdData.hold_id }),
      { headers }
    );
    const orderData = JSON.parse(orderRes.body);
    
    const stockBefore = http.get(`${BASE_URL}/api/products/${PRODUCT_ID}`);
    const stockBeforeData = JSON.parse(stockBefore.body);
    console.log(`Stock before concurrent webhooks: ${stockBeforeData.total_stock}`);
    
    // Send 5 webhooks in parallel with same idempotency key
    const idempotencyKey = `concurrent-${Date.now()}`;
    const webhookPayload = JSON.stringify({
      idempotency_key: idempotencyKey,
      order_id: orderData.order_id,
      status: 'success',
      payload: { transaction_id: 'txn_concurrent' },
    });
    
    console.log('\nSending 5 webhooks concurrently with same idempotency key...');
    
    const responses = http.batch([
      ['POST', `${BASE_URL}/api/payments/webhook`, webhookPayload, { headers }],
      ['POST', `${BASE_URL}/api/payments/webhook`, webhookPayload, { headers }],
      ['POST', `${BASE_URL}/api/payments/webhook`, webhookPayload, { headers }],
      ['POST', `${BASE_URL}/api/payments/webhook`, webhookPayload, { headers }],
      ['POST', `${BASE_URL}/api/payments/webhook`, webhookPayload, { headers }],
    ]);
    
    // Check all responses
    let processedCount = 0;
    let alreadyProcessedCount = 0;
    const webhookIds = new Set();
    
    responses.forEach((res, idx) => {
      const body = JSON.parse(res.body);
      webhookIds.add(body.webhook_id);
      
      if (body.already_processed === false) {
        processedCount++;
      } else {
        alreadyProcessedCount++;
      }
      
      console.log(`   Response ${idx + 1}: already_processed=${body.already_processed}, webhook_id=${body.webhook_id}`);
    });
    
    console.log(`\nResults:`);
    console.log(`   Newly Processed: ${processedCount}`);
    console.log(`   Already Processed: ${alreadyProcessedCount}`);
    console.log(`   Unique Webhook IDs: ${webhookIds.size}`);
    
    const stockAfter = http.get(`${BASE_URL}/api/products/${PRODUCT_ID}`);
    const stockAfterData = JSON.parse(stockAfter.body);
    console.log(`Stock after concurrent webhooks: ${stockAfterData.total_stock}`);
    
    const stockChange = stockBeforeData.total_stock - stockAfterData.total_stock;
    console.log(`Stock change: ${stockChange}`);
    
    if (webhookIds.size !== 1) {
      console.error('\n❌ Multiple webhook records created for same idempotency key!');
      idempotencyViolations.add(1);
    } else if (stockChange !== 1) {
      console.error(`\n❌ Stock changed by ${stockChange} instead of 1!`);
      idempotencyViolations.add(1);
    } else {
      console.log('\n✅ SUCCESS: Concurrent webhooks handled correctly!');
    }
  });
}