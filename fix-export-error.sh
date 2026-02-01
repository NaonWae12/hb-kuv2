#!/bin/bash
# Script untuk memperbaiki error export di VPS

VPS_USER="root"
VPS_HOST="202.10.47.245"
VPS_PATH="/var/www/my_project"
SSH_KEY="$HOME/.ssh/hbku_vps"

echo "=========================================="
echo "  PERBAIKAN ERROR EXPORT DI VPS"
echo "=========================================="
echo ""

# Step 1: Upload files
echo "[1/7] Uploading files to VPS..."
scp -i "$SSH_KEY" -o StrictHostKeyChecking=no routes/web.php "$VPS_USER@$VPS_HOST:$VPS_PATH/routes/web.php" && echo "  ✓ routes/web.php uploaded" || echo "  ✗ Failed to upload routes/web.php"
scp -i "$SSH_KEY" -o StrictHostKeyChecking=no resources/views/forms/responses.blade.php "$VPS_USER@$VPS_HOST:$VPS_PATH/resources/views/forms/responses.blade.php" && echo "  ✓ responses.blade.php uploaded" || echo "  ✗ Failed to upload responses.blade.php"
scp -i "$SSH_KEY" -o StrictHostKeyChecking=no resources/views/forms/create.blade.php "$VPS_USER@$VPS_HOST:$VPS_PATH/resources/views/forms/create.blade.php" && echo "  ✓ create.blade.php uploaded" || echo "  ✗ Failed to upload create.blade.php"
echo ""

# Step 2: Delete compiled views
echo "[2/7] Deleting compiled views..."
ssh -i "$SSH_KEY" -o StrictHostKeyChecking=no "$VPS_USER@$VPS_HOST" "cd $VPS_PATH && rm -rf storage/framework/views/* && echo '  ✓ Compiled views deleted'" || echo "  ✗ Failed to delete compiled views"
echo ""

# Step 3: Clear all caches
echo "[3/7] Clearing all caches..."
ssh -i "$SSH_KEY" -o StrictHostKeyChecking=no "$VPS_USER@$VPS_HOST" "cd $VPS_PATH && php artisan optimize:clear && echo '  ✓ All caches cleared'" || echo "  ✗ Failed to clear caches"
echo ""

# Step 4: Rebuild caches
echo "[4/7] Rebuilding caches..."
ssh -i "$SSH_KEY" -o StrictHostKeyChecking=no "$VPS_USER@$VPS_HOST" "cd $VPS_PATH && php artisan config:cache && php artisan route:cache && php artisan view:cache && echo '  ✓ All caches rebuilt'" || echo "  ✗ Failed to rebuild caches"
echo ""

# Step 5: Set permissions
echo "[5/7] Setting permissions..."
ssh -i "$SSH_KEY" -o StrictHostKeyChecking=no "$VPS_USER@$VPS_HOST" "cd $VPS_PATH && chown -R www-data:www-data resources/views storage && chmod -R 775 storage && echo '  ✓ Permissions set'" || echo "  ✗ Failed to set permissions"
echo ""

# Step 6: Restart services
echo "[6/7] Restarting services..."
ssh -i "$SSH_KEY" -o StrictHostKeyChecking=no "$VPS_USER@$VPS_HOST" "systemctl restart php8.3-fpm && systemctl reload nginx && echo '  ✓ Services restarted'" || echo "  ✗ Failed to restart services"
echo ""

# Step 7: Verification
echo "[7/7] Verifying fixes..."
ssh -i "$SSH_KEY" -o StrictHostKeyChecking=no "$VPS_USER@$VPS_HOST" "cd $VPS_PATH && echo '  Checking routes:' && php artisan route:list | grep export && echo '' && echo '  Checking for forms.export in compiled views:' && find storage/framework/views -name '*.php' -exec grep -l 'forms.export' {} \; 2>/dev/null | wc -l | xargs -I {} echo '  Found {} files with forms.export (0 = GOOD)'" || echo "  ✗ Failed to verify"
echo ""

echo "=========================================="
echo "  PERBAIKAN SELESAI!"
echo "=========================================="
echo ""
echo "Silakan test website:"
echo "  - https://hb-ku.site/forms/3/edit"
echo "  - https://hb-ku.site/forms/3/responses"
echo ""


