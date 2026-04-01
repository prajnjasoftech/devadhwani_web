<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeityController;
use App\Http\Controllers\Api\PoojaController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\DailyPoojaController;
use App\Http\Controllers\Api\DevoteeController;
use App\Http\Controllers\Api\NakshatraController;
use App\Http\Controllers\Api\VendorController;
use App\Http\Controllers\Api\PurchaseCategoryController;
use App\Http\Controllers\Api\PurchasePurposeController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\ExpenseCategoryController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\DonationHeadController;
use App\Http\Controllers\Api\AssetTypeController;
use App\Http\Controllers\Api\DonationController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\EmployeeSalaryController;
use App\Http\Controllers\Api\EmployeePaymentController;
use App\Http\Controllers\Api\LedgerController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TempleController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');

// Authenticated routes
Route::middleware(['auth:sanctum', 'temple.active'])->group(function () {
    // Auth routes (no password reset check needed)
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->name('auth.reset-password');
    Route::put('/auth/profile', [AuthController::class, 'updateProfile'])->name('auth.update-profile');

    // Routes that require password reset check
    Route::middleware(['password.reset'])->group(function () {
        // Dashboard
        Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');

        // Calendar (Panchang)
        Route::get('/calendar/panchang', [CalendarController::class, 'getPanchang'])->name('calendar.panchang');
        Route::get('/calendar/month', [CalendarController::class, 'getMonthData'])->name('calendar.month');

        // Nakshathras (read-only for all authenticated users)
        Route::get('/nakshathras', [NakshatraController::class, 'index'])->name('nakshathras.index');

        // Devotees
        Route::get('/devotees/search', [DevoteeController::class, 'search'])->name('devotees.search');
        Route::get('/devotees', [DevoteeController::class, 'index'])->name('devotees.index');
        Route::get('/devotees/{devotee}', [DevoteeController::class, 'show'])->name('devotees.show');
        Route::post('/devotees', [DevoteeController::class, 'store'])->name('devotees.store');

        // Permissions (read-only for all authenticated users)
        Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');

        // Temples (Platform Admin only)
        Route::middleware(['platform.admin'])->group(function () {
            Route::get('/temples', [TempleController::class, 'index'])->name('temples.index');
            Route::post('/temples', [TempleController::class, 'store'])->name('temples.store');
            Route::get('/temples/stats', [TempleController::class, 'stats'])->name('temples.stats');
            Route::get('/temples/{temple}', [TempleController::class, 'show'])->name('temples.show');
            Route::put('/temples/{temple}', [TempleController::class, 'update'])->name('temples.update');
            Route::delete('/temples/{temple}', [TempleController::class, 'destroy'])->name('temples.destroy');
        });

        // My Temple (Temple users - view and edit their own temple)
        Route::get('/my-temple', [TempleController::class, 'myTemple'])->name('temple.my');
        Route::put('/my-temple', [TempleController::class, 'updateMyTemple'])->name('temple.my.update');

        // Users
        Route::middleware(['permission:users,read'])->group(function () {
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::get('/users/stats', [UserController::class, 'stats'])->name('users.stats');
            Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        });
        Route::post('/users', [UserController::class, 'store'])
            ->middleware('permission:users,create')
            ->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])
            ->middleware('permission:users,update')
            ->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])
            ->middleware('permission:users,delete')
            ->name('users.destroy');

        // Roles
        Route::middleware(['permission:roles,read'])->group(function () {
            Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
            Route::get('/roles/all', [RoleController::class, 'all'])->name('roles.all');
            Route::get('/roles/{role}', [RoleController::class, 'show'])->name('roles.show');
        });
        Route::post('/roles', [RoleController::class, 'store'])
            ->middleware('permission:roles,create')
            ->name('roles.store');
        Route::put('/roles/{role}', [RoleController::class, 'update'])
            ->middleware('permission:roles,update')
            ->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])
            ->middleware('permission:roles,delete')
            ->name('roles.destroy');

        // Deities
        Route::middleware(['permission:deities,read'])->group(function () {
            Route::get('/deities', [DeityController::class, 'index'])->name('deities.index');
            Route::get('/deities/all', [DeityController::class, 'all'])->name('deities.all');
            Route::get('/deities/{deity}', [DeityController::class, 'show'])->name('deities.show');
        });
        Route::post('/deities', [DeityController::class, 'store'])
            ->middleware('permission:deities,create')
            ->name('deities.store');
        Route::put('/deities/{deity}', [DeityController::class, 'update'])
            ->middleware('permission:deities,update')
            ->name('deities.update');
        Route::delete('/deities/{deity}', [DeityController::class, 'destroy'])
            ->middleware('permission:deities,delete')
            ->name('deities.destroy');

        // Poojas
        Route::middleware(['permission:poojas,read'])->group(function () {
            Route::get('/poojas', [PoojaController::class, 'index'])->name('poojas.index');
            Route::get('/poojas/all', [PoojaController::class, 'all'])->name('poojas.all');
            Route::get('/poojas/{pooja}', [PoojaController::class, 'show'])->name('poojas.show');
        });
        Route::post('/poojas', [PoojaController::class, 'store'])
            ->middleware('permission:poojas,create')
            ->name('poojas.store');
        Route::put('/poojas/{pooja}', [PoojaController::class, 'update'])
            ->middleware('permission:poojas,update')
            ->name('poojas.update');
        Route::delete('/poojas/{pooja}', [PoojaController::class, 'destroy'])
            ->middleware('permission:poojas,delete')
            ->name('poojas.destroy');

        // Bookings
        Route::middleware(['permission:bookings,read'])->group(function () {
            Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
            Route::get('/bookings/stats', [BookingController::class, 'stats'])->name('bookings.stats');
            Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
            Route::get('/bookings/{booking}/payments', [BookingController::class, 'payments'])->name('bookings.payments');
        });
        Route::post('/bookings', [BookingController::class, 'store'])
            ->middleware('permission:bookings,create')
            ->name('bookings.store');
        Route::put('/bookings/{booking}', [BookingController::class, 'update'])
            ->middleware('permission:bookings,update')
            ->name('bookings.update');
        Route::delete('/bookings/{booking}', [BookingController::class, 'destroy'])
            ->middleware('permission:bookings,delete')
            ->name('bookings.destroy');
        Route::post('/bookings/{booking}/payments', [BookingController::class, 'addPayment'])
            ->middleware('permission:bookings,update')
            ->name('bookings.add-payment');

        // Daily Poojas
        Route::middleware(['permission:daily_poojas,read'])->group(function () {
            Route::get('/daily-poojas', [DailyPoojaController::class, 'index'])->name('daily-poojas.index');
            Route::get('/daily-poojas/upcoming', [DailyPoojaController::class, 'upcoming'])->name('daily-poojas.upcoming');
        });
        Route::post('/daily-poojas/{schedule}/complete', [DailyPoojaController::class, 'complete'])
            ->middleware('permission:daily_poojas,update')
            ->name('daily-poojas.complete');
        Route::post('/daily-poojas/batch-complete', [DailyPoojaController::class, 'batchComplete'])
            ->middleware('permission:daily_poojas,update')
            ->name('daily-poojas.batch-complete');

        // Vendors
        Route::middleware(['permission:purchases,read'])->group(function () {
            Route::get('/vendors', [VendorController::class, 'index'])->name('vendors.index');
            Route::get('/vendors/all', [VendorController::class, 'all'])->name('vendors.all');
            Route::get('/vendors/{vendor}', [VendorController::class, 'show'])->name('vendors.show');
        });
        Route::post('/vendors', [VendorController::class, 'store'])
            ->middleware('permission:purchases,create')
            ->name('vendors.store');
        Route::put('/vendors/{vendor}', [VendorController::class, 'update'])
            ->middleware('permission:purchases,update')
            ->name('vendors.update');
        Route::delete('/vendors/{vendor}', [VendorController::class, 'destroy'])
            ->middleware('permission:purchases,delete')
            ->name('vendors.destroy');

        // Purchase Categories
        Route::middleware(['permission:purchases,read'])->group(function () {
            Route::get('/purchase-categories', [PurchaseCategoryController::class, 'index'])->name('purchase-categories.index');
            Route::get('/purchase-categories/all', [PurchaseCategoryController::class, 'all'])->name('purchase-categories.all');
            Route::get('/purchase-categories/{purchaseCategory}', [PurchaseCategoryController::class, 'show'])->name('purchase-categories.show');
        });
        Route::post('/purchase-categories', [PurchaseCategoryController::class, 'store'])
            ->middleware('permission:purchases,create')
            ->name('purchase-categories.store');
        Route::put('/purchase-categories/{purchaseCategory}', [PurchaseCategoryController::class, 'update'])
            ->middleware('permission:purchases,update')
            ->name('purchase-categories.update');
        Route::delete('/purchase-categories/{purchaseCategory}', [PurchaseCategoryController::class, 'destroy'])
            ->middleware('permission:purchases,delete')
            ->name('purchase-categories.destroy');

        // Purchase Purposes
        Route::middleware(['permission:purchases,read'])->group(function () {
            Route::get('/purchase-purposes', [PurchasePurposeController::class, 'index'])->name('purchase-purposes.index');
            Route::get('/purchase-purposes/all', [PurchasePurposeController::class, 'all'])->name('purchase-purposes.all');
            Route::get('/purchase-purposes/{purchasePurpose}', [PurchasePurposeController::class, 'show'])->name('purchase-purposes.show');
        });
        Route::post('/purchase-purposes', [PurchasePurposeController::class, 'store'])
            ->middleware('permission:purchases,create')
            ->name('purchase-purposes.store');
        Route::put('/purchase-purposes/{purchasePurpose}', [PurchasePurposeController::class, 'update'])
            ->middleware('permission:purchases,update')
            ->name('purchase-purposes.update');
        Route::delete('/purchase-purposes/{purchasePurpose}', [PurchasePurposeController::class, 'destroy'])
            ->middleware('permission:purchases,delete')
            ->name('purchase-purposes.destroy');

        // Purchases
        Route::middleware(['permission:purchases,read'])->group(function () {
            Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
            Route::get('/purchases/stats', [PurchaseController::class, 'stats'])->name('purchases.stats');
            Route::get('/purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');
        });
        Route::post('/purchases', [PurchaseController::class, 'store'])
            ->middleware('permission:purchases,create')
            ->name('purchases.store');
        Route::put('/purchases/{purchase}', [PurchaseController::class, 'update'])
            ->middleware('permission:purchases,update')
            ->name('purchases.update');
        Route::delete('/purchases/{purchase}', [PurchaseController::class, 'destroy'])
            ->middleware('permission:purchases,delete')
            ->name('purchases.destroy');
        Route::post('/purchases/{purchase}/payment', [PurchaseController::class, 'addPayment'])
            ->middleware('permission:purchases,update')
            ->name('purchases.add-payment');

        // Expense Categories
        Route::middleware(['permission:expenses,read'])->group(function () {
            Route::get('/expense-categories', [ExpenseCategoryController::class, 'index'])->name('expense-categories.index');
            Route::get('/expense-categories/all', [ExpenseCategoryController::class, 'all'])->name('expense-categories.all');
            Route::get('/expense-categories/{expenseCategory}', [ExpenseCategoryController::class, 'show'])->name('expense-categories.show');
        });
        Route::post('/expense-categories', [ExpenseCategoryController::class, 'store'])
            ->middleware('permission:expenses,create')
            ->name('expense-categories.store');
        Route::put('/expense-categories/{expenseCategory}', [ExpenseCategoryController::class, 'update'])
            ->middleware('permission:expenses,update')
            ->name('expense-categories.update');
        Route::delete('/expense-categories/{expenseCategory}', [ExpenseCategoryController::class, 'destroy'])
            ->middleware('permission:expenses,delete')
            ->name('expense-categories.destroy');

        // Expenses
        Route::middleware(['permission:expenses,read'])->group(function () {
            Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
            Route::get('/expenses/stats', [ExpenseController::class, 'stats'])->name('expenses.stats');
            Route::get('/expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');
        });
        Route::post('/expenses', [ExpenseController::class, 'store'])
            ->middleware('permission:expenses,create')
            ->name('expenses.store');
        Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])
            ->middleware('permission:expenses,update')
            ->name('expenses.update');
        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])
            ->middleware('permission:expenses,delete')
            ->name('expenses.destroy');
        Route::post('/expenses/{expense}/payment', [ExpenseController::class, 'addPayment'])
            ->middleware('permission:expenses,update')
            ->name('expenses.add-payment');

        // Accounts (Temple users - Super Admin only for setup)
        Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
        Route::get('/accounts/all', [AccountController::class, 'all'])->name('accounts.all');
        Route::get('/accounts/balances', [AccountController::class, 'balances'])->name('accounts.balances');
        Route::get('/accounts/check-setup', [AccountController::class, 'checkSetup'])->name('accounts.check-setup');
        Route::post('/accounts/setup', [AccountController::class, 'setup'])->name('accounts.setup');
        Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
        Route::put('/accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');

        // Ledger (Super Admin only - accessible to all temple users for viewing)
        Route::get('/ledger', [LedgerController::class, 'index'])->name('ledger.index');
        Route::get('/ledger/stats', [LedgerController::class, 'stats'])->name('ledger.stats');
        Route::get('/ledger/statement', [LedgerController::class, 'statement'])->name('ledger.statement');
        Route::get('/ledger/balance-sheet', [LedgerController::class, 'balanceSheet'])->name('ledger.balance-sheet');
        Route::post('/ledger/transfer', [LedgerController::class, 'transfer'])->name('ledger.transfer');
        Route::get('/ledger/{ledgerEntry}', [LedgerController::class, 'show'])->name('ledger.show');

        // Donation Heads
        Route::middleware(['permission:donations,read'])->group(function () {
            Route::get('/donation-heads', [DonationHeadController::class, 'index'])->name('donation-heads.index');
            Route::get('/donation-heads/all', [DonationHeadController::class, 'all'])->name('donation-heads.all');
            Route::get('/donation-heads/{donationHead}', [DonationHeadController::class, 'show'])->name('donation-heads.show');
        });
        Route::post('/donation-heads', [DonationHeadController::class, 'store'])
            ->middleware('permission:donations,create')
            ->name('donation-heads.store');
        Route::put('/donation-heads/{donationHead}', [DonationHeadController::class, 'update'])
            ->middleware('permission:donations,update')
            ->name('donation-heads.update');
        Route::delete('/donation-heads/{donationHead}', [DonationHeadController::class, 'destroy'])
            ->middleware('permission:donations,delete')
            ->name('donation-heads.destroy');

        // Asset Types
        Route::middleware(['permission:donations,read'])->group(function () {
            Route::get('/asset-types', [AssetTypeController::class, 'index'])->name('asset-types.index');
            Route::get('/asset-types/all', [AssetTypeController::class, 'all'])->name('asset-types.all');
            Route::get('/asset-types/{assetType}', [AssetTypeController::class, 'show'])->name('asset-types.show');
        });
        Route::post('/asset-types', [AssetTypeController::class, 'store'])
            ->middleware('permission:donations,create')
            ->name('asset-types.store');
        Route::put('/asset-types/{assetType}', [AssetTypeController::class, 'update'])
            ->middleware('permission:donations,update')
            ->name('asset-types.update');
        Route::delete('/asset-types/{assetType}', [AssetTypeController::class, 'destroy'])
            ->middleware('permission:donations,delete')
            ->name('asset-types.destroy');

        // Donations
        Route::middleware(['permission:donations,read'])->group(function () {
            Route::get('/donations', [DonationController::class, 'index'])->name('donations.index');
            Route::get('/donations/stats', [DonationController::class, 'stats'])->name('donations.stats');
            Route::get('/donations/{donation}', [DonationController::class, 'show'])->name('donations.show');
        });
        Route::post('/donations', [DonationController::class, 'store'])
            ->middleware('permission:donations,create')
            ->name('donations.store');
        Route::put('/donations/{donation}', [DonationController::class, 'update'])
            ->middleware('permission:donations,update')
            ->name('donations.update');
        Route::delete('/donations/{donation}', [DonationController::class, 'destroy'])
            ->middleware('permission:donations,delete')
            ->name('donations.destroy');

        // Assets (Asset Register)
        Route::middleware(['permission:donations,read'])->group(function () {
            Route::get('/assets', [AssetController::class, 'index'])->name('assets.index');
            Route::get('/assets/stats', [AssetController::class, 'stats'])->name('assets.stats');
            Route::get('/assets/{asset}', [AssetController::class, 'show'])->name('assets.show');
        });
        Route::post('/assets', [AssetController::class, 'store'])
            ->middleware('permission:donations,create')
            ->name('assets.store');
        Route::post('/assets/from-donation', [AssetController::class, 'createFromDonation'])
            ->middleware('permission:donations,create')
            ->name('assets.from-donation');
        Route::put('/assets/{asset}', [AssetController::class, 'update'])
            ->middleware('permission:donations,update')
            ->name('assets.update');
        Route::delete('/assets/{asset}', [AssetController::class, 'destroy'])
            ->middleware('permission:donations,delete')
            ->name('assets.destroy');

        // Employees
        Route::middleware(['permission:employees,read'])->group(function () {
            Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
            Route::get('/employees/all', [EmployeeController::class, 'all'])->name('employees.all');
            Route::get('/employees/stats', [EmployeeController::class, 'stats'])->name('employees.stats');
            Route::get('/employees/designations', [EmployeeController::class, 'designations'])->name('employees.designations');
            Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
        });
        Route::post('/employees', [EmployeeController::class, 'store'])
            ->middleware('permission:employees,create')
            ->name('employees.store');
        Route::put('/employees/{employee}', [EmployeeController::class, 'update'])
            ->middleware('permission:employees,update')
            ->name('employees.update');
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])
            ->middleware('permission:employees,delete')
            ->name('employees.destroy');

        // Employee Salaries
        Route::middleware(['permission:employees,read'])->group(function () {
            Route::get('/employee-salaries', [EmployeeSalaryController::class, 'index'])->name('employee-salaries.index');
            Route::get('/employee-salaries/stats', [EmployeeSalaryController::class, 'stats'])->name('employee-salaries.stats');
            Route::get('/employee-salaries/{employeeSalary}', [EmployeeSalaryController::class, 'show'])->name('employee-salaries.show');
        });
        Route::post('/employee-salaries', [EmployeeSalaryController::class, 'store'])
            ->middleware('permission:employees,create')
            ->name('employee-salaries.store');
        Route::post('/employee-salaries/generate', [EmployeeSalaryController::class, 'generateMonthly'])
            ->middleware('permission:employees,create')
            ->name('employee-salaries.generate');
        Route::put('/employee-salaries/{employeeSalary}', [EmployeeSalaryController::class, 'update'])
            ->middleware('permission:employees,update')
            ->name('employee-salaries.update');
        Route::post('/employee-salaries/{employeeSalary}/pay', [EmployeeSalaryController::class, 'pay'])
            ->middleware('permission:employees,update')
            ->name('employee-salaries.pay');
        Route::delete('/employee-salaries/{employeeSalary}', [EmployeeSalaryController::class, 'destroy'])
            ->middleware('permission:employees,delete')
            ->name('employee-salaries.destroy');

        // Employee Payments (non-salary)
        Route::middleware(['permission:employees,read'])->group(function () {
            Route::get('/employee-payments', [EmployeePaymentController::class, 'index'])->name('employee-payments.index');
            Route::get('/employee-payments/stats', [EmployeePaymentController::class, 'stats'])->name('employee-payments.stats');
            Route::get('/employee-payments/{employeePayment}', [EmployeePaymentController::class, 'show'])->name('employee-payments.show');
        });
        Route::post('/employee-payments', [EmployeePaymentController::class, 'store'])
            ->middleware('permission:employees,create')
            ->name('employee-payments.store');
        Route::put('/employee-payments/{employeePayment}', [EmployeePaymentController::class, 'update'])
            ->middleware('permission:employees,update')
            ->name('employee-payments.update');
        Route::delete('/employee-payments/{employeePayment}', [EmployeePaymentController::class, 'destroy'])
            ->middleware('permission:employees,delete')
            ->name('employee-payments.destroy');
    });
});
