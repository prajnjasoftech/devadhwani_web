# Devadhwani - Temple Management System

## Project Overview
Multi-tenant Laravel + Vue + MySQL temple management application with API-first architecture.

## Technology Stack
- **Backend**: Laravel 11, PHP 8.2, Sanctum authentication
- **Frontend**: Vue 3 (Composition API), Vite, Tailwind CSS, Pinia
- **Database**: MySQL with single-DB multi-tenancy via `temple_id`

## Current Modules

### 1. Authentication & Access Control
- Login with contact_number + password
- Force password reset on first login
- Platform Admin vs Temple Users
- Role-based permissions

### 2. Temple Management (Platform Admin only)
- CRUD temples with auto-creation of Super Admin user
- Temple status management (active/inactive/suspended)

### 3. User Management (Temple Users)
- Create/edit users within temple scope
- Assign roles to users

### 4. Role Management (Temple Users)
- Create roles with granular permissions
- System role for Super Admin (is_system_role = true)
- **IMPORTANT**: Super Admin has ALL permissions. When creating a new module with permissions, always add them to the Super Admin role (role with `is_system_role = true`)

### 5. Deity Management
- Temple-specific deities
- Optional for poojas

### 6. Pooja Management
- Define poojas with pricing
- Optional deity association
- `devotee_required` flag for quantity-based poojas
- CSV import support

### 7. Booking Management
- Create bookings with multiple pooja items
- Devotee/beneficiary management
- Payment tracking (partial/full)
- Occurrence tracking (daily poojas)

### 8. Purchase Management
- **Vendors**: Supplier/vendor master with description field
- **Categories**: Item categories (Flowers, Ghee, etc.)
- **Purposes**: Purchase purposes (Daily, Festival, Special)
- **Purchases**: Track purchases with payment status

### 9. Expense Management
- **Expense Categories**: Categorize expenses (Electricity, Salary, Maintenance, etc.)
- **Expenses**: Track non-purchase expenses with payment status
- Separate from Purchases (for items bought vs general expenses)

### 10. Accounts Management
- **One-time Setup**: Initial setup for opening balances (cannot be changed after)
- **Cash Account**: Single cash account per temple
- **Bank Accounts**: Multiple bank accounts with optional UPI marking
- **Balance Tracking**: Current balances auto-updated with transactions
- **Super Admin Only**: Only temple Super Admin can access accounts

### 11. Donation Management
- **Donation Heads**: Categories for donations (General, Annadanam, Temple Renovation)
- **Asset Types**: Types of asset donations (Gold, Silver, Land, Vehicle)
- **Financial Donations**: Cash, UPI, Bank Transfer, Cheque - credits to accounts
- **Asset Donations**: Gold, Silver, etc. with quantity and estimated value
- Auto-generated donation numbers: `{TEMPLE_CODE}/DON/YYYYMM/0001`

### 12. Asset Register
- **Asset Inventory**: Track all temple assets in one place
- **Acquisition Types**: Existing (already owned), Donation, Purchase
- **Asset Details**: Type, quantity, value, condition, location
- **Link to Donations**: Assets from donations can be linked
- Auto-generated asset numbers: `{TEMPLE_CODE}/AST/YYYY/0001`

### 13. Employee Management
- **Employee Master**: Name, designation, contact, salary, bank details, ID proofs
- **Optional App User**: Create employee as application user with role assignment
- **Salary Management**: Generate monthly salaries, track payments
- **Other Payments**: Bonus, advance, reimbursement, incentive payments
- Auto-generated employee codes: `{TEMPLE_CODE}/EMP/0001`
- Default password for app users: `Employee@123`

### 14. Ledger
- **Transaction Ledger**: All financial credits and debits in one place
- **Auto-recording**: Entries created automatically when transactions occur
- **Running Balance**: Balance after each transaction per account
- **Source Types**: booking, donation, purchase, expense, salary, employee_payment, opening_balance
- **Account Statement**: Per-account statement with date range
- **Balance Sheet**: Summary of all account balances as of a date
- Auto-generated entry numbers: `{TEMPLE_CODE}/LED/YYYYMM/0001`
- **Super Admin Only**: Only temple Super Admin can access ledger

### 15. Calendar (Malayalam Panchang)
- **Monthly Calendar View**: Interactive calendar grid with navigation
- **Malayalam Date Display**: Shows Kolla Varsham date for each day
- **Panchang Details**: Click any date to view full Panchang information
- **Data Includes**:
  - Sunrise, Sunset, Moonrise, Moonset
  - Vaara (Day of week in Malayalam)
  - Tithi (Lunar day) with Paksha
  - Nakshatra (Star) with Lord
  - Yoga, Karana
  - Auspicious timings: Brahma Muhurat, Abhijit Muhurat
  - Inauspicious timings: Rahu Kaal, Yamaganda Kaal, Gulika Kaal, Dur Muhurat
- **API**: Uses Prokerala Astrology API (`prokerala/astrology-sdk`)
- **Available to all temple users** (no permission required)

## Key Files

### Backend
- `app/Traits/BelongsToTemple.php` - Multi-tenancy trait
- `app/Services/TempleService.php` - Temple + Super Admin creation
- `app/Services/BookingService.php` - Booking creation logic
- `app/Services/LedgerService.php` - Ledger entry management
- `app/Services/ProkeralaService.php` - Prokerala Astrology API client
- `app/Http/Middleware/TempleScope.php` - Temple scoping middleware

### Frontend
- `resources/js/router/index.js` - Route definitions with guards
- `resources/js/stores/auth.js` - Authentication state
- `resources/js/components/layout/Sidebar.vue` - Navigation

## Database Migrations (Latest)
- `2026_03_30_154234_create_vendors_table.php`
- `2026_03_30_154240_create_purchase_categories_table.php`
- `2026_03_30_154247_create_purchase_purposes_table.php`
- `2026_03_30_154253_create_purchases_table.php`
- `2026_03_30_170523_add_description_to_vendors_table.php`
- `2026_03_31_100001_create_expense_categories_table.php`
- `2026_03_31_100002_create_expenses_table.php`
- `2026_03_31_110001_create_accounts_table.php`
- `2026_03_31_120001_create_donation_heads_table.php`
- `2026_03_31_120002_create_asset_types_table.php`
- `2026_03_31_120003_create_donations_table.php`
- `2026_03_31_130001_create_assets_table.php`
- `2026_03_31_140001_create_employees_table.php`
- `2026_03_31_140002_create_employee_salaries_table.php`
- `2026_03_31_140003_create_employee_payments_table.php`
- `2026_04_01_150001_create_ledger_entries_table.php`
- `2026_04_01_150002_add_account_id_to_booking_payments_table.php`
- `2026_04_01_150003_add_account_id_to_purchases_table.php`
- `2026_04_01_150004_add_account_id_to_expenses_table.php`

## UI Patterns
- Separate form pages (not modals) for create/edit
- Consistent action icons: PencilIcon (edit), NoSymbolIcon/CheckCircleIcon (toggle status)
- Cards with sections for form organization
- Table with custom slot templates

## Routes Pattern
```
/module           - List view
/module/create    - Create form (or /module/new)
/module/:id/edit  - Edit form
/module/:id       - Detail view (where applicable)
```

## Commands
```bash
php artisan migrate          # Run migrations
php artisan db:seed          # Seed permissions + platform admin
php artisan tinker           # Interactive shell
npm run dev                  # Start Vite dev server
```

## Default Platform Admin
- Contact: 9999999999
- Password: Admin@123 (must reset on first login)

## Adding New Modules - IMPORTANT

When creating a new module with permissions:

1. **Create permissions in seeder** (`database/seeders/PermissionSeeder.php`)
2. **Add permissions to Super Admin role**: Always assign new permissions to all system roles (`is_system_role = true`)

```php
// In seeder or migration, after creating permissions:
$newPermissionIds = Permission::where('module_key', 'new_module')->pluck('id');
$systemRoles = Role::where('is_system_role', true)->get();
foreach ($systemRoles as $role) {
    $role->permissions()->syncWithoutDetaching($newPermissionIds);
}
```

**Super Admin must have ALL permissions to ALL modules - no exceptions.**
