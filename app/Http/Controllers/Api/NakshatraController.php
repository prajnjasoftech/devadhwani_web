<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Nakshathra;
use Illuminate\Http\JsonResponse;

class NakshatraController extends Controller
{
    /**
     * Get all nakshathras (ordered list)
     */
    public function index(): JsonResponse
    {
        $nakshathras = Nakshathra::active()
            ->ordered()
            ->get(['id', 'name', 'malayalam_name', 'order']);

        return response()->json([
            'success' => true,
            'data' => $nakshathras,
        ]);
    }
}
