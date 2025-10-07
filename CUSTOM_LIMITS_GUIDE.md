# 🎯 Custom VPN Limits - Complete Guide

Now you can set custom limits for each client and company, instead of using defaults!

---

## 🆕 What's New?

### ✅ **Custom Limits per Client**
Each client (staff member) can now have their own VPN limit!

### ✅ **Unlimited Options**
Set limits to "unlimited" by leaving them empty (NULL)

### ✅ **Flexible System**
- Company-wide defaults (optional)
- Per-client custom limits (optional)
- Mix and match as needed

---

## 🔢 How It Works Now

### **Three-Level Limit System:**

```
┌─────────────────────────────────────────┐
│ ADMIN                                   │
│ ├─ Sets limits for each customer       │
│ │  • max_clients (can be NULL)         │
│ │  • max_vpn_per_client (can be NULL)  │
│ └─ NULL = unlimited                     │
└─────────────────────────────────────────┘
         ↓
┌─────────────────────────────────────────┐
│ CUSTOMER (Company)                      │
│ ├─ Uses admin's default limits          │
│ ├─ Can set custom limit per client      │
│ └─ Overrides default for that client    │
└─────────────────────────────────────────┘
         ↓
┌─────────────────────────────────────────┐
│ CLIENT (Individual Staff)               │
│ ├─ Has custom limit OR                  │
│ ├─ Uses company default OR               │
│ └─ Unlimited (if nothing set)           │
└─────────────────────────────────────────┘
```

---

## 👨‍💼 Admin: Setting Customer Limits

### Option 1: With Limits (Controlled)

```
Customers → Add/Edit Customer

Company: ABC Corp
Max Clients: 10               ← Can create 10 clients
Max VPN per Client: 3         ← Default: 3 VPN per client
```

**Result:**
- ABC Corp can create 10 clients
- Each client gets 3 VPN accounts by default
- Customer can set custom limits per client

### Option 2: Unlimited Clients

```
Max Clients: [leave empty]    ← Unlimited clients!
Max VPN per Client: 3         ← But each client limited to 3 VPN
```

**Result:**
- ABC Corp can create unlimited clients
- Each client still limited to 3 VPN accounts

### Option 3: Completely Unlimited

```
Max Clients: [leave empty]         ← Unlimited clients
Max VPN per Client: [leave empty]  ← Unlimited VPN per client
```

**Result:**
- ABC Corp can create unlimited clients
- Each client can have unlimited VPN accounts
- Customer can still set custom limits per client if needed

---

## 👥 Customer: Setting Per-Client Limits

### When Adding a Client:

```
My Clients → Add Client

Client Name: John Doe
Email: john@company.com
Department: IT

Max VPN Accounts: [5]     ← Custom limit for John only!
                          Or leave empty for default
```

**What happens:**
- Leave empty → Uses company default (3 VPN) or unlimited
- Set to 5 → John can have 5 VPN accounts (overrides default)
- Set to 10 → John can have 10 VPN accounts

### When Editing a Client:

```
Click Edit on any client

Max VPN Accounts: [10]    ← Change John's limit
```

**Result:**
- John now has custom limit of 10 VPN accounts
- Other clients still use company default

---

## 📊 Examples

### Example 1: Small Company - No Limits

**Admin sets:**
```
max_clients = NULL (unlimited)
max_vpn_per_client = NULL (unlimited)
```

**Customer can:**
- Create unlimited clients
- Each client: unlimited VPN by default
- But can set custom limits: "Sales team: 2 VPN, IT team: 10 VPN"

---

### Example 2: Medium Company - Default with Custom

**Admin sets:**
```
max_clients = 50
max_vpn_per_client = 3
```

**Customer creates:**
```
Client: Alice (IT Manager)
  Custom Limit: 10 VPN     ← Special access

Client: Bob (Sales)
  Custom Limit: [empty]    ← Uses default (3 VPN)

Client: Charlie (Support)
  Custom Limit: 2 VPN      ← Limited access
```

**Result:**
- Alice: 10 VPN accounts
- Bob: 3 VPN accounts (default)
- Charlie: 2 VPN accounts

---

### Example 3: Enterprise - Mixed Limits

**Admin sets:**
```
max_clients = NULL (unlimited)
max_vpn_per_client = 5 (default)
```

**Customer creates:**
```
Executives: Custom 20 VPN each
Managers: Custom 10 VPN each
Staff: Use default (5 VPN)
Contractors: Custom 1 VPN each
```

---

## 🎯 VPN Creation Dropdown

### What You'll See:

```
Select Client:
  Alice (IT Manager)    [3/10]             ← Custom limit
  Bob (Sales)           [2/3]              ← Default limit
  Charlie (Support)     [2/2] [Limit Reached]  ← At limit
  David (Developer)     [5/unlimited]      ← No limit set
```

**Legend:**
- `[3/10]` = 3 VPN accounts used out of 10 allowed
- `[2/3]` = 2 out of 3 (company default)
- `[2/2] [Limit Reached]` = Cannot create more (disabled)
- `[5/unlimited]` = No limit

---

## 🔍 Checking Current Limits

### As Customer:

**Dashboard shows:**
```
Client Members (15 / unlimited)   ← No client limit
                  ↑
            Can add more freely
```

**VPN Accounts dropdown:**
- Shows each client's current usage
- Shows their custom or default limit
- Automatically disables if at limit

### As Admin:

**Customers page:**
```
Company     | Clients         | VPN
ABC Corp    | 15/unlimited    | 45 VPN
XYZ Ltd     | 8/20            | 24 VPN
```

---

## 💾 Database Changes

### New Column: `client_accounts.max_vpn_accounts`

```sql
max_vpn_accounts INT DEFAULT NULL

Values:
- NULL = Use customer default or unlimited
- Number = Custom limit for this client
```

### Updated Columns: `customers` table

```sql
max_clients INT DEFAULT NULL
  NULL = Unlimited clients

max_vpn_per_client INT DEFAULT NULL
  NULL = Unlimited VPN (default for new clients)
  Number = Default limit for clients
```

---

## 🔧 How to Set Limits

### Remove All Limits (Unlimited):

**As Admin:**
```sql
-- Via phpMyAdmin or SQL
UPDATE customers 
SET max_clients = NULL, 
    max_vpn_per_client = NULL 
WHERE id = CUSTOMER_ID;
```

**Or via Admin Panel:**
- Edit customer
- Leave both fields empty
- Save

### Set Custom Limit for One Client:

**As Customer:**
1. My Clients → Click client
2. Set "Max VPN Accounts": 10
3. Save

**Result:** That client can have 10 VPN accounts

### Set Limit for All New Clients (Default):

**As Admin:**
1. Customers → Edit customer
2. Set "Max VPN per Client": 5
3. Save

**Result:** All new clients will default to 5 VPN (unless custom limit set)

---

## 📝 Best Practices

### For Admins:

1. **Start with NULL (Unlimited)**
   - Let customers manage their own limits
   - Add restrictions only if needed

2. **Or Set Reasonable Defaults**
   - max_clients: 20-50 for SME
   - max_vpn_per_client: 3-5

3. **Monitor Usage**
   - Check which customers need more
   - Adjust as needed

### For Customers:

1. **Use Custom Limits Wisely**
   - Executives/IT: Higher limits (10-20)
   - Regular staff: Use default (3-5)
   - Contractors/Temp: Lower limits (1-2)

2. **Review Regularly**
   - Check who's using what
   - Adjust limits based on actual usage

3. **Document Your Policy**
   - "Executives: 10 VPN"
   - "Staff: 3 VPN"
   - "Contractors: 1 VPN"

---

## ❓ FAQ

**Q: What if I leave max_clients empty?**
A: Customer can create unlimited clients!

**Q: What if I leave max_vpn_per_client empty?**
A: All clients can have unlimited VPN accounts (unless custom limit set)

**Q: Can I set different limits for different clients?**
A: Yes! Set custom limit when adding/editing each client

**Q: What happens if I change the company default?**
A: Only affects NEW clients. Existing clients keep their limits

**Q: Can a client have more than the company default?**
A: Yes! Set a custom limit higher than the default

**Q: Can I remove a custom limit from a client?**
A: Yes! Edit client, leave "Max VPN Accounts" empty, save

**Q: What does NULL vs 0 mean?**
A: 
- NULL = Unlimited
- 0 = Cannot create any (blocked)
- Number = That many allowed

---

## 🎯 Quick Reference

### Scenarios:

| Admin Sets | Customer Sets | Client Can Have |
|-----------|---------------|-----------------|
| NULL | NULL | Unlimited VPN |
| NULL | 5 | 5 VPN |
| 3 | NULL | 3 VPN (default) |
| 3 | 10 | 10 VPN (custom) |
| 3 | 1 | 1 VPN (custom) |

### Priority:

```
1. Client's custom limit (if set)
   ↓ (if not set)
2. Customer's default limit (if set)
   ↓ (if not set)
3. Unlimited
```

---

## 🔄 Migration Applied

The database has been updated with:

```sql
✅ client_accounts.max_vpn_accounts added
✅ customers.max_clients allows NULL
✅ customers.max_vpn_per_client allows NULL
```

**Existing data:**
- All existing customers: Keep their current limits
- All existing clients: No custom limits (use defaults)

---

## 🎉 Summary

**You now have:**

1. ✅ **Unlimited option** - Set any limit to NULL
2. ✅ **Custom per-client limits** - Different limits for each staff
3. ✅ **Flexible defaults** - Company-wide defaults optional
4. ✅ **Easy management** - Set limits in Add/Edit forms
5. ✅ **Clear display** - See limits in dropdowns
6. ✅ **Smart enforcement** - Only enforced if set

**No more forced defaults!** 🎊

You decide:
- Unlimited for everyone
- Default limits for most, custom for some
- Strict limits for all
- Mix and match!

---

**Need help? Check the examples above or test it out!**

Happy VPN Management! 🚀

