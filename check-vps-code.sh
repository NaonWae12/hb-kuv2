#!/bin/bash
# Script untuk download file dari VPS untuk dibandingkan dengan lokal

VPS_USER="root"
VPS_HOST="202.10.47.245"
VPS_PATH="/var/www/my_project"
SSH_KEY="$HOME/.ssh/hbku_vps"
LOCAL_DIR="./vps-files-check"

echo "Creating directory: $LOCAL_DIR"
mkdir -p "$LOCAL_DIR"

echo ""
echo "=== Downloading files from VPS ==="
echo ""

# Download routes
echo "1. Downloading routes/web.php..."
scp -i "$SSH_KEY" -o StrictHostKeyChecking=no "$VPS_USER@$VPS_HOST:$VPS_PATH/routes/web.php" "$LOCAL_DIR/routes-web.php" 2>/dev/null && echo "   ✓ routes/web.php downloaded" || echo "   ✗ Failed to download routes/web.php"

# Download FormController
echo "2. Downloading FormController.php..."
scp -i "$SSH_KEY" -o StrictHostKeyChecking=no "$VPS_USER@$VPS_HOST:$VPS_PATH/app/Http/Controllers/FormController.php" "$LOCAL_DIR/FormController.php" 2>/dev/null && echo "   ✓ FormController.php downloaded" || echo "   ✗ Failed to download FormController.php"

# Download responses.blade.php
echo "3. Downloading responses.blade.php..."
scp -i "$SSH_KEY" -o StrictHostKeyChecking=no "$VPS_USER@$VPS_HOST:$VPS_PATH/resources/views/forms/responses.blade.php" "$LOCAL_DIR/responses.blade.php" 2>/dev/null && echo "   ✓ responses.blade.php downloaded" || echo "   ✗ Failed to download responses.blade.php"

# Download create.blade.php
echo "4. Downloading create.blade.php..."
scp -i "$SSH_KEY" -o StrictHostKeyChecking=no "$VPS_USER@$VPS_HOST:$VPS_PATH/resources/views/forms/create.blade.php" "$LOCAL_DIR/create.blade.php" 2>/dev/null && echo "   ✓ create.blade.php downloaded" || echo "   ✗ Failed to download create.blade.php"

echo ""
echo "=== Checking for forms.export in VPS files ==="
echo ""

# Check routes
echo "Checking routes/web.php for 'forms.export':"
if grep -q "forms.export" "$LOCAL_DIR/routes-web.php" 2>/dev/null; then
    echo "   ✗ FOUND 'forms.export' in routes/web.php (BAD!)"
    grep -n "forms.export" "$LOCAL_DIR/routes-web.php"
else
    echo "   ✓ No 'forms.export' found in routes/web.php (GOOD)"
fi

# Check views
echo ""
echo "Checking responses.blade.php for 'forms.export':"
if grep -q "forms.export" "$LOCAL_DIR/responses.blade.php" 2>/dev/null; then
    echo "   ✗ FOUND 'forms.export' in responses.blade.php (BAD!)"
    grep -n "forms.export" "$LOCAL_DIR/responses.blade.php"
else
    echo "   ✓ No 'forms.export' found in responses.blade.php (GOOD)"
fi

echo ""
echo "Checking create.blade.php for 'forms.export':"
if grep -q "forms.export" "$LOCAL_DIR/create.blade.php" 2>/dev/null; then
    echo "   ✗ FOUND 'forms.export' in create.blade.php (BAD!)"
    grep -n "forms.export" "$LOCAL_DIR/create.blade.php"
else
    echo "   ✓ No 'forms.export' found in create.blade.php (GOOD)"
fi

echo ""
echo "=== Files downloaded to: $LOCAL_DIR ==="
echo "You can now compare these files with your local files:"
echo "  - $LOCAL_DIR/routes-web.php vs routes/web.php"
echo "  - $LOCAL_DIR/FormController.php vs app/Http/Controllers/FormController.php"
echo "  - $LOCAL_DIR/responses.blade.php vs resources/views/forms/responses.blade.php"
echo "  - $LOCAL_DIR/create.blade.php vs resources/views/forms/create.blade.php"


