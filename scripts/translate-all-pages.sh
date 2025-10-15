#!/bin/bash

# Script to add language support to all portal pages
# This script adds the language.php include to all main pages

echo "🌐 Adding language support to all portal pages..."

# Files to update
files=(
    "customer/vpn-accounts.php"
    "customer/clients.php"
    "customer/staff.php"
    "customer/change-password.php"
    "admin/index.php"
    "admin/customers.php"
    "admin/vpn-accounts.php"
    "admin/servers.php"
    "admin/vpn-pool.php"
    "admin/activity-logs.php"
    "admin/clients.php"
    "admin/staff.php"
)

for file in "${files[@]}"; do
    if [ -f "$file" ]; then
        # Check if language.php is already included
        if ! grep -q "includes/language.php" "$file"; then
            echo "  ✅ Adding language support to $file"
            # Add language.php include after the first <?php line
            sed -i '' '1 a\
require_once __DIR__ . '\''/../includes/language.php'\'';
' "$file"
        else
            echo "  ⏭️  $file already has language support"
        fi
    fi
done

echo ""
echo "✅ Language support added to all pages!"
echo "🔄 Next: Update HTML lang attribute and translate text strings"

