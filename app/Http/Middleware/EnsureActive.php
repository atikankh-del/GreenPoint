<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureActive
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && $request->user()->fresh()->status !== 'active') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'บัญชีนี้ถูกระงับ กรุณาติดต่อผู้ดูแล']);
        }

        if ($request->user()?->role !== 'admin' && \App\Models\SystemSetting::valueOf('maintenance_mode','0')==='1' && !$request->routeIs('logout')) {
            return response()->view('maintenance',[],503);
        }
        return $next($request);
    }
}
