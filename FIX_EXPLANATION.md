The issue is that the `t()` helper function is defined conditionally with `if (!function_exists('t'))` which can cause problems when accessing pages with `&lang=bn` parameter.

The fix is to define the `t()` function globally without the conditional check, ensuring it's always available for translations.

Here's what needs to be changed:

**File: `classes/Helpers.php`**

**Problem:**
The `t()` function is defined conditionally:
```php
if (!function_exists('t')) {
    function t($key, $default = null) {
        return Lang::get($key, $default);
    }
}
```

This conditional definition can cause issues because:
1. The function might not be available when needed
2. It can lead to inconsistent behavior across different contexts
3. Translation fallbacks might fail in certain scenarios

**Solution:**
Define the `t()` function globally without the conditional check:
```php
// Global helper function
function t($key, $default = null) {
    return Lang::get($key, $default);
}
```

**Why this fixes the issue:**
When the receipt page loads with `lang=bn`, the `t()` function is needed to translate keys like:
- 'received_from'
- 'phone' 
- 'address'
- 'note' or 'comment'
- 'total' or 'amount'

If `t()` is not defined, these translations will fail and show in English. By defining it globally, the translation system works consistently.

**Additional context:**
The `receipt.php` file uses `t()` for many labels that should display in the correct language:
- Line 41: `t('received_from')` - "Received from"
- Line 155: `t('phone')` - "Phone"
- Line 160: `t('address')` - "Address"  
- Line 177: `t('note')` - "Comments/note"
- Line 44: `t('total')` - "Total/amount"

**Testing the fix:**
After making the change, verify by:
1. Accessing `http://localhost/oop-rms/receipt.php?id=1&lang=bn`
2. Checking that labels show in Bengali (বাংলা)
3. Confirming English translations are available via `lang=en`