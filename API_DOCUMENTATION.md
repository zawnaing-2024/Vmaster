# 📱 VMaster Mobile App API Documentation

## Base URL
```
Production: https://your-domain.com
Local: http://localhost:8000
```

---

## 🔐 Authentication

All API requests (except login) require a Bearer token in the Authorization header:

```http
Authorization: Bearer YOUR_TOKEN_HERE
```

---

## 📋 API Endpoints

### 1. Client Login

**Endpoint:** `POST /api/client/login.php`

**Description:** Authenticate a client and get access token

**Request:**
```json
{
  "username": "john_doe",
  "password": "password123"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "token": "eyJjbGllbnRfaWQiOjEyMy...",
  "client_id": 123,
  "client_name": "John Doe",
  "customer_company": "ABC Corporation",
  "message": "Login successful"
}
```

**Error Response (401):**
```json
{
  "success": false,
  "message": "Invalid username or password"
}
```

**Example (cURL):**
```bash
curl -X POST http://localhost:8000/api/client/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"john_doe","password":"password123"}'
```

**Example (JavaScript):**
```javascript
const response = await fetch('http://localhost:8000/api/client/login.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    username: 'john_doe',
    password: 'password123'
  })
});

const data = await response.json();
console.log(data.token);
```

---

### 2. Get VPN Accounts

**Endpoint:** `GET /api/client/vpn-accounts.php`

**Description:** Get all VPN accounts for the authenticated client

**Headers:**
```http
Authorization: Bearer YOUR_TOKEN_HERE
```

**Success Response (200):**
```json
{
  "success": true,
  "count": 3,
  "accounts": [
    {
      "id": 1,
      "server_name": "US-01",
      "server_type": "outline",
      "server_host": "us.vpn.com",
      "server_port": 443,
      "location": "United States",
      "status": "active",
      "expiration_status": "active",
      "plan_duration": 2,
      "expires_at": "2025-12-09 23:59:59",
      "days_remaining": 61,
      "created_at": "2025-10-09 10:00:00",
      "access_key": "ss://Y2hhY2hhMjAtaWV0Zi1wb2x5MTMwNTpwYXNzd29yZA==@us.vpn.com:443",
      "protocol": "shadowsocks"
    },
    {
      "id": 2,
      "server_name": "UK-02",
      "server_type": "sstp",
      "server_host": "uk.vpn.com",
      "server_port": 443,
      "location": "United Kingdom",
      "status": "active",
      "expiration_status": "active",
      "plan_duration": 3,
      "expires_at": "2026-01-09 23:59:59",
      "days_remaining": 92,
      "created_at": "2025-10-09 11:00:00",
      "username": "sstp_user123",
      "password": "SecurePass123!",
      "protocol": "sstp"
    },
    {
      "id": 3,
      "server_name": "JP-03",
      "server_type": "v2ray",
      "server_host": "jp.vpn.com",
      "server_port": 10086,
      "location": "Japan",
      "status": "active",
      "expiration_status": "active",
      "plan_duration": 6,
      "expires_at": "2026-04-09 23:59:59",
      "days_remaining": 183,
      "created_at": "2025-10-09 12:00:00",
      "access_key": "vmess://eyJ2IjoiMiIsInBzIjoiSlAtMDMi...",
      "protocol": "vmess",
      "v2ray_config": {
        "v": "2",
        "ps": "JP-03",
        "add": "jp.vpn.com",
        "port": 10086,
        "id": "uuid-here",
        "aid": 0,
        "net": "tcp",
        "type": "none",
        "tls": "tls"
      }
    }
  ]
}
```

**Error Response (401):**
```json
{
  "success": false,
  "message": "Invalid token"
}
```

**Example (cURL):**
```bash
curl -X GET http://localhost:8000/api/client/vpn-accounts.php \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

**Example (JavaScript):**
```javascript
const response = await fetch('http://localhost:8000/api/client/vpn-accounts.php', {
  method: 'GET',
  headers: {
    'Authorization': 'Bearer ' + token
  }
});

const data = await response.json();
console.log(data.accounts);
```

---

### 3. Report Connection Status

**Endpoint:** `POST /api/client/connection-status.php`

**Description:** Report VPN connection status from mobile app

**Headers:**
```http
Authorization: Bearer YOUR_TOKEN_HERE
Content-Type: application/json
```

**Request:**
```json
{
  "account_id": 1,
  "status": "connected",
  "connected_at": "2025-10-09 18:30:00",
  "ip_address": "103.x.x.x"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Status reported successfully"
}
```

**Error Response (400):**
```json
{
  "success": false,
  "message": "VPN account not found or access denied"
}
```

**Example (cURL):**
```bash
curl -X POST http://localhost:8000/api/client/connection-status.php \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "account_id": 1,
    "status": "connected",
    "connected_at": "2025-10-09 18:30:00",
    "ip_address": "103.x.x.x"
  }'
```

---

## 🧪 Testing the API

### Option 1: Web-Based Tester (Easiest)

Open in browser:
```
http://localhost:8000/api/test-mobile-api.html
```

1. Enter client username and password
2. Click "Test Login" → Get token
3. Token auto-fills in other sections
4. Click "Get VPN Accounts" → See all accounts
5. Click "Report Status" → Test status reporting

### Option 2: Using cURL

```bash
# 1. Login
TOKEN=$(curl -s -X POST http://localhost:8000/api/client/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"john_doe","password":"password123"}' \
  | jq -r '.token')

echo "Token: $TOKEN"

# 2. Get VPN accounts
curl -X GET http://localhost:8000/api/client/vpn-accounts.php \
  -H "Authorization: Bearer $TOKEN" | jq

# 3. Report status
curl -X POST http://localhost:8000/api/client/connection-status.php \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "account_id": 1,
    "status": "connected",
    "connected_at": "2025-10-09 18:30:00"
  }' | jq
```

### Option 3: Using Postman

1. Import collection (create from examples above)
2. Set base URL variable
3. Test each endpoint
4. Save token as environment variable

---

## 🔒 Security Notes

### Token Format:
```json
{
  "client_id": 123,
  "customer_id": 456,
  "username": "john_doe",
  "issued_at": 1696867200,
  "expires_at": 1699459200
}
```

Token is base64 encoded JSON (simple implementation).

**For production, consider:**
- JWT (JSON Web Tokens) with signature
- Token refresh mechanism
- Rate limiting
- IP whitelisting

### HTTPS Required:
- ⚠️ Always use HTTPS in production
- Credentials transmitted in plain text over HTTP
- Use SSL certificate

---

## 📱 Android App Integration Example

### Login Flow:

```kotlin
// ApiService.kt
interface ApiService {
    @POST("api/client/login.php")
    suspend fun login(@Body request: LoginRequest): LoginResponse
    
    @GET("api/client/vpn-accounts.php")
    suspend fun getVpnAccounts(
        @Header("Authorization") token: String
    ): VpnAccountsResponse
}

// LoginViewModel.kt
class LoginViewModel : ViewModel() {
    fun login(username: String, password: String) {
        viewModelScope.launch {
            try {
                val response = apiService.login(
                    LoginRequest(username, password)
                )
                
                if (response.success) {
                    // Save token
                    prefsManager.saveToken(response.token)
                    
                    // Navigate to dashboard
                    _loginState.value = LoginState.Success
                }
            } catch (e: Exception) {
                _loginState.value = LoginState.Error(e.message)
            }
        }
    }
}

// DashboardViewModel.kt
class DashboardViewModel : ViewModel() {
    fun loadAccounts() {
        viewModelScope.launch {
            val token = prefsManager.getToken()
            val response = apiService.getVpnAccounts("Bearer $token")
            
            if (response.success) {
                _accounts.value = response.accounts
            }
        }
    }
}
```

---

## 🎯 Response Codes

| Code | Meaning | Description |
|------|---------|-------------|
| 200 | OK | Request successful |
| 201 | Created | Resource created |
| 400 | Bad Request | Invalid input |
| 401 | Unauthorized | Invalid or missing token |
| 403 | Forbidden | Access denied |
| 404 | Not Found | Resource not found |
| 405 | Method Not Allowed | Wrong HTTP method |
| 500 | Server Error | Internal server error |

---

## 📊 Data Models

### VPN Account Object:
```json
{
  "id": 1,
  "server_name": "US-01",
  "server_type": "outline|sstp|v2ray",
  "server_host": "server.com",
  "server_port": 443,
  "location": "United States",
  "status": "active|suspended|inactive",
  "expiration_status": "active|expired|unlimited",
  "plan_duration": 2,
  "expires_at": "2025-12-09 23:59:59",
  "days_remaining": 61,
  "created_at": "2025-10-09 10:00:00",
  
  // Protocol-specific fields:
  "access_key": "ss://..." | "vmess://...",  // For Outline/V2Ray
  "username": "sstp_user",                    // For SSTP
  "password": "password",                     // For SSTP
  "protocol": "shadowsocks|sstp|vmess"
}
```

---

## 🚀 Quick Start for Developers

### 1. Test API Locally:
```bash
# Open API tester
open http://localhost:8000/api/test-mobile-api.html

# Or test with cURL
curl -X POST http://localhost:8000/api/client/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"test","password":"test"}'
```

### 2. Start Android Project:
```bash
# Create new Android project in Android Studio
# Package name: com.vmaster.vpn
# Min SDK: 21 (Android 5.0)
# Language: Kotlin
```

### 3. Add Dependencies:
```gradle
// In app/build.gradle
dependencies {
    // Retrofit for API
    implementation 'com.squareup.retrofit2:retrofit:2.9.0'
    implementation 'com.squareup.retrofit2:converter-gson:2.9.0'
    
    // VPN libraries (add as needed)
    implementation 'com.github.shadowsocks:shadowsocks-android:5.3.1'
    
    // UI
    implementation 'androidx.compose.ui:ui:1.5.4'
    implementation 'androidx.compose.material3:material3:1.1.2'
}
```

### 4. Implement API Client:
```kotlin
object ApiClient {
    private const val BASE_URL = "https://your-domain.com/"
    
    val retrofit: Retrofit = Retrofit.Builder()
        .baseUrl(BASE_URL)
        .addConverterFactory(GsonConverterFactory.create())
        .build()
    
    val apiService: ApiService = retrofit.create(ApiService::class.java)
}
```

---

## 📝 Notes

### Current Limitations:
1. **Simple Token** - Uses base64 encoding (upgrade to JWT recommended)
2. **No Password for Clients** - Currently uses staff_name as username
3. **No Token Refresh** - Token expires after 30 days

### Recommended Improvements:
1. Add `password` column to `client_accounts` table
2. Implement JWT with signature
3. Add token refresh endpoint
4. Add rate limiting
5. Add API versioning

---

## 🔧 Database Changes Needed

To support mobile app login, add password field to clients:

```sql
-- Add password column to client_accounts
ALTER TABLE client_accounts 
ADD COLUMN app_password VARCHAR(255) DEFAULT NULL 
COMMENT 'Password for mobile app login';

-- Create index for faster lookups
CREATE INDEX idx_staff_name ON client_accounts(staff_name);
```

---

## 🎉 Summary

**API Endpoints Created:**
- ✅ `/api/client/login.php` - Client authentication
- ✅ `/api/client/vpn-accounts.php` - Get VPN accounts
- ✅ `/api/client/connection-status.php` - Report connection status

**Testing Tool:**
- ✅ `/api/test-mobile-api.html` - Web-based API tester

**Documentation:**
- ✅ `API_DOCUMENTATION.md` - This file
- ✅ `ANDROID_VPN_APP_PLAN.md` - App development plan

---

**Ready to build your Android app!** 🚀

Test the API first at: `http://localhost:8000/api/test-mobile-api.html`

