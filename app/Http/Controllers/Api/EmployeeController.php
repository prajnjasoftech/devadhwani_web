<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Models\Role;
use App\Rules\IndianMobile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Employee::query()
            ->with(['user:id,name,contact_number'])
            ->search($request->search);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('designation')) {
            $query->where('designation', $request->designation);
        }

        $employees = $query
            ->orderBy('name')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $employees->items(),
            'meta' => [
                'current_page' => $employees->currentPage(),
                'last_page' => $employees->lastPage(),
                'per_page' => $employees->perPage(),
                'total' => $employees->total(),
            ],
        ]);
    }

    public function all(): JsonResponse
    {
        $employees = Employee::active()
            ->orderBy('name')
            ->get(['id', 'employee_code', 'name', 'designation']);

        return response()->json([
            'success' => true,
            'data' => $employees,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'designation' => 'required|string|max:255',
            'contact_number' => ['required', 'string', new IndianMobile],
            'alternate_contact' => ['nullable', 'string', new IndianMobile],
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'date_of_joining' => 'required|date',
            'basic_salary' => 'required|numeric|min:0',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:50',
            'ifsc_code' => 'nullable|string|max:20',
            'pan_number' => 'nullable|string|max:20',
            'aadhaar_number' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
            'create_user' => 'boolean',
            'role_id' => 'required_if:create_user,true|nullable|exists:roles,id',
        ]);

        $validated['temple_id'] = auth()->user()->temple_id;
        $validated['created_by'] = auth()->id();

        DB::beginTransaction();
        try {
            // Create user if requested
            if ($request->boolean('create_user')) {
                $user = User::create([
                    'name' => $validated['name'],
                    'contact_number' => $validated['contact_number'],
                    'email' => $validated['email'],
                    'password' => Hash::make('Employee@123'), // Default password
                    'user_type' => 'temple_user',
                    'temple_id' => $validated['temple_id'],
                    'role_id' => $validated['role_id'],
                    'must_reset_password' => true,
                ]);
                $validated['user_id'] = $user->id;
            }

            unset($validated['create_user'], $validated['role_id']);
            $employee = Employee::create($validated);
            $employee->load('user:id,name,contact_number');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Employee created successfully' . ($request->boolean('create_user') ? '. Default password: Employee@123' : ''),
                'data' => $employee,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create employee: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show(Employee $employee): JsonResponse
    {
        $employee->load(['user:id,name,contact_number,email', 'creator:id,name']);

        return response()->json([
            'success' => true,
            'data' => $employee,
        ]);
    }

    public function update(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'designation' => 'sometimes|string|max:255',
            'contact_number' => ['sometimes', 'string', new IndianMobile],
            'alternate_contact' => ['nullable', 'string', new IndianMobile],
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'date_of_joining' => 'sometimes|date',
            'date_of_leaving' => 'nullable|date',
            'basic_salary' => 'sometimes|numeric|min:0',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:50',
            'ifsc_code' => 'nullable|string|max:20',
            'pan_number' => 'nullable|string|max:20',
            'aadhaar_number' => 'nullable|string|max:20',
            'is_active' => 'sometimes|boolean',
            'notes' => 'nullable|string',
        ]);

        $employee->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Employee updated successfully',
            'data' => $employee,
        ]);
    }

    public function destroy(Employee $employee): JsonResponse
    {
        if ($employee->salaries()->exists() || $employee->payments()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete employee with existing salary or payment records',
            ], 422);
        }

        $employee->delete();

        return response()->json([
            'success' => true,
            'message' => 'Employee deleted successfully',
        ]);
    }

    public function stats(): JsonResponse
    {
        $templeId = auth()->user()->temple_id;

        $totalEmployees = Employee::where('temple_id', $templeId)->where('is_active', true)->count();
        $totalSalary = Employee::where('temple_id', $templeId)->where('is_active', true)->sum('basic_salary');

        // Pending salaries for current month
        $currentMonth = now()->month;
        $currentYear = now()->year;

        $pendingSalaries = \App\Models\EmployeeSalary::where('temple_id', $templeId)
            ->where('year', $currentYear)
            ->where('month', $currentMonth)
            ->whereIn('payment_status', ['pending', 'partial'])
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_employees' => $totalEmployees,
                'total_monthly_salary' => $totalSalary,
                'pending_salaries' => $pendingSalaries,
            ],
        ]);
    }

    public function designations(): JsonResponse
    {
        $designations = Employee::where('temple_id', auth()->user()->temple_id)
            ->distinct()
            ->pluck('designation');

        return response()->json([
            'success' => true,
            'data' => $designations,
        ]);
    }
}
