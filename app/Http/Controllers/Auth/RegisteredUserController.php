<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Tenancy\RegisterShopAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\NepalMobilePhone;
use App\Support\NepaliPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     */
    public function store(Request $request, RegisterShopAction $action): RedirectResponse
    {
        if (is_string($request->input('phone'))) {
            $request->merge(['phone' => NepaliPhone::normalize($request->input('phone'))]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['nullable', 'string', 'max:20', new NepalMobilePhone, 'unique:'.User::class],
            'password' => ['required', 'confirmed', Password::defaults()],
            'shop_name' => ['required', 'string', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:50'],
        ]);

        $result = $action->execute($validated);

        Auth::login($result['user']);
        $request->session()->regenerate();
        $request->session()->put('current_shop_id', $result['shop']->id);

        return redirect()->route('dashboard');
    }
}
