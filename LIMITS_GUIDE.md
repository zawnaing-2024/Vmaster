# 📊 VPN Account Limits Guide

Complete guide for understanding and managing VPN account limits in VMaster.

---

## 🎯 How Limits Work

VMaster has a **three-level limit system** to control VPN account creation:

```
┌─────────────────────────────────────────────────┐
│  ADMIN                                          │
│  ├─ Sets limits for each customer               │
│  └─ Controls overall system                     │
└─────────────────────────────────────────────────┘
         ↓
┌─────────────────────────────────────────────────┐
│  CUSTOMER (Company)                             │
│  ├─ Can create X clients (set by admin)        │
│  ├─ Each client can have Y VPN accounts         │
│  │   (set by admin)                             │
│  └─ Manages their own clients                   │
└─────────────────────────────────────────────────┘
         ↓
┌─────────────────────────────────────────────────┐
│  CLIENT (End User)                              │
│  ├─ Can have Y VPN accounts (max per client)   │
│  └─ Uses VPN to connect                         │
└─────────────────────────────────────────────────┘
```

---

## 🔢 Limit Types

### 1. **Max Clients per Customer**
- **Set by**: Admin when creating/editing customer
- **Field**: `max_clients`
- **Example**: Customer "ABC Corp" can create 10 clients

### 2. **Max VPN Accounts per Client**
- **Set by**: Admin when creating/editing customer
- **Field**: `max_vpn_per_client`
- **Example**: Each client can have 3 VPN accounts

### Example Scenario:
```
Admin creates customer "ABC Corp"
├─ max_clients = 10
└─ max_vpn_per_client = 3

ABC Corp can create:
├─ 10 clients (employees)
└─ Each client can have 3 VPN accounts
    Total possible VPN accounts = 10 × 3 = 30
```

---

## 👤 Admin Side - Setting Limits

### When Creating a Customer:

1. **Login to Admin Panel**
   ```
   http://your-vmaster.com/admin/login.php
   ```

2. **Navigate to Customers → Add Customer**

3. **Set the limits:**
   ```
   Company Name: ABC Corporation
   Full Name: John Doe
   Username: abc_corp
   Email: admin@abc.com
   Phone: +95911111111
   
   📊 Limits:
   Max Clients: 10               ← How many employees can be added
   Max VPN per Client: 3         ← VPN accounts per employee
   
   Status: Active
   ```

4. **Click "Add Customer"**

### Editing Limits:

1. Go to **Customers** page
2. Click **Edit** next to customer
3. Update the limits
4. Save changes

### Viewing Customer Usage:

On the Customers page, you'll see:
```
Company Name    | Clients      | VPN Accounts | Status
ABC Corp        | 5/10 Clients | 12 VPN       | Active
XYZ Ltd         | 8/20 Clients | 20 VPN       | Active
```

---

## 👥 Customer Side - Managing Limits

### Viewing Your Limits:

**Dashboard shows:**
```
┌──────────────────────────┐
│ Total Clients: 5 / 10    │  ← Current / Maximum
│ VPN Accounts: 12         │  ← Total VPN accounts
│ Outline: 8               │
│ V2Ray + SSTP: 4          │
└──────────────────────────┘
```

### Creating Clients:

1. **Go to My Clients**
2. Click **Add Client**

**Limits are checked:**
- ✅ If you have 5/10 clients → Can add more
- ❌ If you have 10/10 clients → Cannot add (limit reached)

```
Client Members (5 / 10)    ← Shows current usage
```

### Creating VPN Accounts:

1. **Go to VPN Accounts**
2. Click **Create VPN Account**
3. **Select Client dropdown** shows:

```
-- Choose Client --
Alice Smith (0/3)           ← Can create 3 more
Bob Johnson (2/3)           ← Can create 1 more
Charlie Brown (3/3) [Limit Reached] ← Disabled
```

**What happens:**
- ✅ Select Alice → Can create VPN account
- ✅ Select Bob → Can create 1 more VPN account
- ❌ Cannot select Charlie → Already at limit (3/3)

---

## ⚠️ Error Messages

### When Creating Client:

**At Limit:**
```
❌ You have reached the maximum number of client accounts 
   allowed (10 clients).
```

**What to do:**
- Contact your admin to increase the limit
- Or delete unused clients

### When Creating VPN Account:

**Client at Limit:**
```
❌ This client has reached the maximum VPN accounts 
   allowed (3 VPN accounts per client).
```

**What to do:**
- Select a different client
- Delete unused VPN accounts
- Contact admin to increase per-client limit

---

## 📝 Examples

### Example 1: Small Company

**Admin sets for "Small Co":**
```
max_clients = 5
max_vpn_per_client = 2
```

**Customer can create:**
- 5 employees (clients)
- Each employee gets 2 VPN accounts
- Total: 10 VPN accounts max

### Example 2: Medium Company

**Admin sets for "Medium Co":**
```
max_clients = 20
max_vpn_per_client = 3
```

**Customer can create:**
- 20 employees
- Each gets 3 VPN accounts (work computer, phone, tablet)
- Total: 60 VPN accounts max

### Example 3: Large Enterprise

**Admin sets for "Enterprise":**
```
max_clients = 100
max_vpn_per_client = 5
```

**Customer can create:**
- 100 employees
- Each gets 5 VPN accounts
- Total: 500 VPN accounts max

---

## 🔍 Checking Current Usage

### As Admin:

```sql
-- Check customer's current usage
SELECT 
    c.company_name,
    c.max_clients,
    (SELECT COUNT(*) FROM client_accounts WHERE customer_id = c.id) as current_clients,
    c.max_vpn_per_client,
    (SELECT COUNT(*) FROM vpn_accounts WHERE customer_id = c.id) as total_vpn_accounts
FROM customers c
WHERE c.id = CUSTOMER_ID;
```

### As Customer (via phpMyAdmin):

```sql
-- Check your client usage
SELECT COUNT(*) as total_clients 
FROM client_accounts 
WHERE customer_id = YOUR_CUSTOMER_ID;

-- Check VPN accounts per client
SELECT 
    ca.client_name,
    COUNT(va.id) as vpn_count
FROM client_accounts ca
LEFT JOIN vpn_accounts va ON ca.id = va.client_id
WHERE ca.customer_id = YOUR_CUSTOMER_ID
GROUP BY ca.id;
```

---

## 🛠️ Modifying Limits

### Increasing Limits:

**As Admin:**
1. Customers → Edit customer
2. Increase `max_clients` or `max_vpn_per_client`
3. Save

**Effect:**
- Customer can immediately create more clients/VPN accounts
- No data is lost
- Existing accounts remain

### Decreasing Limits:

**⚠️ Warning:** Be careful when decreasing limits!

**Safe decrease:**
- Current usage: 5 clients
- New limit: 10 clients → ✅ OK

**Unsafe decrease:**
- Current usage: 15 clients
- New limit: 10 clients → ⚠️ Warning

**What happens:**
- Existing 15 clients remain
- But cannot create new clients until count drops below 10
- Must delete 5 clients to create new ones

---

## 📊 Best Practices

### For Admins:

1. **Start Conservative**
   - Set lower limits initially
   - Increase based on actual usage

2. **Monitor Usage**
   - Check Customers page regularly
   - Look for clients near limits

3. **Plan for Growth**
   - Allow 20-30% headroom
   - Example: If company has 10 employees, set limit to 15

4. **Different Tiers**
   ```
   Small:  max_clients=10,  max_vpn_per_client=2
   Medium: max_clients=50,  max_vpn_per_client=3
   Large:  max_clients=200, max_vpn_per_client=5
   ```

### For Customers:

1. **Track Usage**
   - Regularly check dashboard
   - Plan before hitting limits

2. **Clean Up**
   - Delete inactive clients
   - Remove unused VPN accounts

3. **Request Increases Early**
   - Contact admin before hitting limits
   - Provide justification

---

## 🎯 Limit Check Flow

### Creating Client:

```
User clicks "Add Client"
         ↓
Check: current_clients < max_clients?
         ↓                 ↓
        YES               NO
         ↓                 ↓
    Allow Create     Show Error
         ↓
    Client Created
```

### Creating VPN Account:

```
User selects client
         ↓
Check: client_vpn_count < max_vpn_per_client?
         ↓                 ↓
        YES               NO
         ↓                 ↓
    Allow Create     Disable Option/Show Error
         ↓
    VPN Created
```

---

## 🔐 Database Schema

### Customers Table:
```sql
CREATE TABLE customers (
    id INT PRIMARY KEY,
    company_name VARCHAR(255),
    max_clients INT DEFAULT 10,           ← Max clients limit
    max_vpn_per_client INT DEFAULT 3,     ← Max VPN per client
    ...
);
```

### Checking Limits:
```sql
-- Current clients for a customer
SELECT COUNT(*) FROM client_accounts 
WHERE customer_id = ? AND status = 'active';

-- VPN accounts for a client
SELECT COUNT(*) FROM vpn_accounts 
WHERE client_id = ?;
```

---

## ❓ FAQ

**Q: What happens if I delete a client with VPN accounts?**
A: Their VPN accounts are also deleted automatically.

**Q: Can I have different limits per client?**
A: No, currently all clients under one customer have the same VPN limit.

**Q: Can I temporarily increase limits?**
A: Yes! Admin can edit and change limits anytime.

**Q: Do disabled clients count toward the limit?**
A: Yes, both active and inactive clients count toward `max_clients`.

**Q: Can admin override limits?**
A: No, limits are enforced even for admins creating accounts.

---

## 🎉 Summary

**Three Key Limits:**
1. ✅ **Max Clients** - How many employees per company
2. ✅ **Max VPN per Client** - How many VPN accounts per employee
3. ✅ **Total VPN** - Automatically calculated (clients × vpn_per_client)

**Who Sets What:**
- 👨‍💼 **Admin** → Sets limits for customers
- 👥 **Customer** → Uses limits, can request increases
- 👤 **Client** → End user, receives VPN accounts

**Limits are working!** ✅
- Customer clients page: Shows current/max
- VPN creation: Blocks at limit
- Error messages: Clear and informative

---

**Need to change limits? See admin panel → Customers → Edit**

