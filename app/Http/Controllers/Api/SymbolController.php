<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SymbolResource;
use App\Models\Symbol;

class SymbolController extends Controller
{
    public function index()
    {
        $symbols = Symbol::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return SymbolResource::collection($symbols)
            ->response()
            ->header('Cache-Control', 'public, max-age=60');
    }
}
