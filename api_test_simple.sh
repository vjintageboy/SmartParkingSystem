#!/bin/bash

# Simple API test script for parking system
echo "=== Testing Parking System API ==="

BASE_URL="http://127.0.0.1:8000/api"

# Test 1: Check system status
echo "1. Checking system status..."
curl -s -X GET "$BASE_URL/status" | jq '.'
echo

# Test 2: Vehicle entry
echo "2. Testing vehicle entry with valid RFID..."
curl -s -X POST "$BASE_URL/entry" \
  -H "Content-Type: application/json" \
  -d '{"rfid":"A1B2C3D4"}' | jq '.'
echo

# Test 3: Check status after entry
echo "3. Checking status after entry..."
curl -s -X GET "$BASE_URL/status" | jq '.'
echo

# Test 4: Vehicle exit
echo "4. Testing vehicle exit..."
curl -s -X POST "$BASE_URL/exit" \
  -H "Content-Type: application/json" \
  -d '{"rfid":"A1B2C3D4"}' | jq '.'
echo

# Test 5: Check final status
echo "5. Final status check..."
curl -s -X GET "$BASE_URL/status" | jq '.'
echo

echo "=== API Test Complete ==="
