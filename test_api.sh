#!/bin/bash

echo "=== Testing Parking System API ==="

echo "1. Testing Status API:"
curl -X GET http://127.0.0.1:8000/api/status
echo -e "\n"

echo "2. Testing Entry with valid RFID:"
curl -X POST http://127.0.0.1:8000/api/entry \
  -H "Content-Type: application/json" \
  -d '{"rfid_tag":"A1B2C3D4"}'
echo -e "\n"

echo "3. Testing Status again:"
curl -X GET http://127.0.0.1:8000/api/status
echo -e "\n"

echo "4. Testing Exit with same RFID:"
curl -X POST http://127.0.0.1:8000/api/exit \
  -H "Content-Type: application/json" \
  -d '{"rfid_tag":"A1B2C3D4"}'
echo -e "\n"

echo "5. Testing Entry with invalid RFID:"
curl -X POST http://127.0.0.1:8000/api/entry \
  -H "Content-Type: application/json" \
  -d '{"rfid_tag":"INVALID123"}'
echo -e "\n"

echo "=== API Test Complete ==="
