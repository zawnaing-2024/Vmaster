# 🧪 VMaster Android App - Testing Guide

## 📱 How to Test the Android App

### Prerequisites:
- ✅ Android Studio installed
- ✅ Android device or emulator
- ✅ Backend API running (local or production)
- ✅ Test client account created in VMaster CMS

---

## 🚀 Method 1: Using Android Studio (Recommended)

### Step 1: Install Android Studio

Download from: https://developer.android.com/studio

**System Requirements:**
- Windows 10/11, macOS 10.14+, or Linux
- 8 GB RAM minimum (16 GB recommended)
- 8 GB disk space

### Step 2: Open the Project

```bash
# Open Android Studio
# Click: File → Open
# Navigate to: /Users/zawnainghtun/My Coding Project/VPN CMS Portal/VMasterVPN-Android
# Click: OK
# Wait for Gradle sync (2-5 minutes first time)
```

### Step 3: Configure API URL

Edit: `app/src/main/java/com/vmaster/vpn/utils/Constants.kt`

```kotlin
// For testing on emulator (localhost)
const val API_BASE_URL = "http://10.0.2.2:8000/"

// For testing on physical device (same WiFi)
const val API_BASE_URL = "http://192.168.1.XXX:8000/"  // Replace XXX with your Mac's IP

// For production
const val API_BASE_URL = "https://your-domain.com/"
```

**To find your Mac's IP:**
```bash
ifconfig | grep "inet " | grep -v 127.0.0.1
```

### Step 4: Create Test Client Account

In VMaster CMS web portal:

1. Login as customer
2. Go to "My Clients"
3. Create a test client:
   - Name: Test User
   - Email: test@test.com
4. Create VPN accounts for this client

### Step 5: Run the App

**Option A: On Emulator**
```
1. In Android Studio: Tools → Device Manager
2. Create new device (Pixel 5, API 34)
3. Click: Run → Run 'app'
4. Select emulator
5. Wait for app to install and launch
```

**Option B: On Physical Device**
```
1. Enable Developer Options on your Android phone:
   - Settings → About Phone
   - Tap "Build Number" 7 times
   
2. Enable USB Debugging:
   - Settings → Developer Options
   - Enable "USB Debugging"
   
3. Connect phone to computer via USB

4. In Android Studio:
   - Click: Run → Run 'app'
   - Select your device
   - App installs and launches
```

### Step 6: Test the App

1. **Splash Screen** → Should show for 1.5 seconds
2. **Login Screen** → Enter test client username/password
3. **Dashboard** → Should show list of VPN accounts
4. **Click account** → Should attempt to connect (will show "TODO" for now)
5. **Pull to refresh** → Reload accounts
6. **Menu → Logout** → Return to login

---

## 🧪 Method 2: Build and Install APK

### Step 1: Build APK

```bash
# In Android Studio
Build → Build Bundle(s) / APK(s) → Build APK(s)

# Or via command line
cd VMasterVPN-Android
./gradlew assembleDebug
```

APK location:
```
app/build/outputs/apk/debug/app-debug.apk
```

### Step 2: Install on Device

**Option A: Via USB**
```bash
# Connect device via USB
# Enable USB debugging on device

# Install APK
adb install app/build/outputs/apk/debug/app-debug.apk

# Or
adb install -r app/build/outputs/apk/debug/app-debug.apk  # Replace if exists
```

**Option B: Transfer APK**
```bash
# Copy APK to device
# Open file manager on device
# Tap APK file
# Allow "Install from unknown sources"
# Install
```

---

## 🔍 Testing Checklist

### ✅ Backend API Testing:

```bash
# Test API first
open http://localhost:8000/api/test-mobile-api.html

1. Test login with client credentials
2. Get VPN accounts
3. Verify response format
4. All should work ✅
```

### ✅ App Testing:

#### 1. Login Flow:
- [ ] App launches with splash screen
- [ ] Navigates to login screen
- [ ] Can enter username and password
- [ ] Login button works
- [ ] Shows loading indicator
- [ ] Success → Navigate to dashboard
- [ ] Error → Shows error message

#### 2. Dashboard:
- [ ] Shows list of VPN accounts
- [ ] Each account shows:
  - [ ] Server name
  - [ ] Server type (Outline/SSTP/V2Ray)
  - [ ] Location
  - [ ] Expiry date
  - [ ] Connect button
- [ ] Pull to refresh works
- [ ] Empty state shows if no accounts

#### 3. VPN Connection (Limited):
- [ ] Click account → Shows "TODO" message
- [ ] (VPN logic not implemented yet)

#### 4. Menu:
- [ ] Refresh → Reloads accounts
- [ ] Logout → Returns to login

---

## 🐛 Troubleshooting

### Issue 1: "Cannot connect to API"

**Check API URL:**
```kotlin
// In Constants.kt
// For emulator:
const val API_BASE_URL = "http://10.0.2.2:8000/"

// For physical device:
const val API_BASE_URL = "http://YOUR_MAC_IP:8000/"
```

**Find your Mac IP:**
```bash
ifconfig | grep "inet " | grep -v 127.0.0.1
# Use the IP shown (e.g., 192.168.1.100)
```

**Test API manually:**
```bash
# From your phone's browser
http://YOUR_MAC_IP:8000/api/test-mobile-api.html
```

### Issue 2: "Gradle sync failed"

**Solution:**
```
File → Invalidate Caches → Invalidate and Restart
```

### Issue 3: "App crashes on launch"

**Check logs:**
```
View → Tool Windows → Logcat
Filter by: com.vmaster.vpn
```

### Issue 4: "Cannot install APK"

**Enable unknown sources:**
```
Settings → Security → Allow installation from unknown sources
```

### Issue 5: "Login fails"

**Verify:**
1. Backend API is running
2. API URL is correct
3. Client account exists in database
4. Network connection is working

**Test API directly:**
```bash
curl -X POST http://YOUR_API_URL/api/client/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"test","password":"test"}'
```

---

## 📊 What to Expect

### Current Status (85% Complete):
- ✅ Login works
- ✅ Dashboard loads
- ✅ VPN accounts display
- ✅ UI looks good
- ⏳ VPN connection shows "TODO" (not implemented yet)

### What Works:
```
✅ Splash screen
✅ Login authentication
✅ Token storage
✅ Fetch VPN accounts
✅ Display accounts list
✅ Show server details
✅ Show expiry dates
✅ Pull to refresh
✅ Logout
```

### What Doesn't Work Yet:
```
⏳ Actual VPN connection (shows "TODO")
⏳ Connection status indicator
⏳ Speed test
⏳ Data usage tracking
```

---

## 🎯 Testing Scenarios

### Scenario 1: First Time User

```
1. Install app
2. Open app → Splash screen
3. Login screen appears
4. Enter username: test_client
5. Enter password: test123
6. Click LOGIN
7. Dashboard appears with VPN accounts
✅ Success!
```

### Scenario 2: Returning User

```
1. Open app
2. Splash screen
3. Auto-login → Dashboard
4. VPN accounts loaded
✅ Success!
```

### Scenario 3: Pull to Refresh

```
1. On dashboard
2. Pull down on accounts list
3. Loading indicator shows
4. Accounts refresh
✅ Success!
```

### Scenario 4: Logout

```
1. Click menu (⋮)
2. Click "Logout"
3. Returns to login screen
4. Token cleared
✅ Success!
```

---

## 📱 Device Testing

### Test on Multiple Devices:

1. **Emulator** (Android Studio)
   - Pixel 5, API 34
   - Test basic functionality

2. **Physical Device** (Your Phone)
   - Real network conditions
   - Better performance testing
   - Actual VPN testing

3. **Different Android Versions**
   - Android 5.0 (API 21) - Minimum
   - Android 10 (API 29) - Common
   - Android 14 (API 34) - Latest

---

## 🔧 Debug Tools

### Logcat (View Logs):
```
Android Studio → View → Tool Windows → Logcat
Filter: com.vmaster.vpn
```

### Network Inspector:
```
View → Tool Windows → App Inspection → Network Inspector
See all API calls in real-time
```

### Layout Inspector:
```
Tools → Layout Inspector
View UI hierarchy
```

---

## ✅ Success Criteria

App is working if:
- ✅ Login succeeds with valid credentials
- ✅ Dashboard shows VPN accounts
- ✅ Accounts display correct information
- ✅ UI is responsive and smooth
- ✅ Pull to refresh works
- ✅ Logout works
- ✅ No crashes

---

## 📝 Test Report Template

```
Date: ___________
Tester: ___________
Device: ___________
Android Version: ___________

✅ Splash Screen: PASS / FAIL
✅ Login: PASS / FAIL
✅ Dashboard Load: PASS / FAIL
✅ VPN List Display: PASS / FAIL
✅ Pull to Refresh: PASS / FAIL
✅ Logout: PASS / FAIL

Issues Found:
1. ___________
2. ___________

Notes:
___________
```

---

## 🎉 Current Status

**What You Can Test Now:**
- ✅ Complete login flow
- ✅ Dashboard with VPN accounts
- ✅ UI/UX experience
- ✅ API integration
- ✅ Data display

**What Needs Developer:**
- ⏳ Actual VPN connection (Outline, SSTP, V2Ray)
- ⏳ Connection notifications
- ⏳ Advanced features

---

## 🚀 Quick Start Testing

```bash
# 1. Open Android Studio
open -a "Android Studio" VMasterVPN-Android

# 2. Wait for Gradle sync

# 3. Click Run button (▶️)

# 4. Select device

# 5. App launches!
```

---

**Ready to test!** 🎉

**The app is 85% complete and testable now!**

