<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use App\Models\Role;
use App\Models\ShopUser;
use App\Rules\NepalMobilePhone;
use App\Tenancy\TenantContext;
use App\Traits\NormalizesNepaliInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreStaffMemberRequest extends FormRequest
{
    use NormalizesNepaliInput;

    public function authorize(): bool
    {
        return $this->user()?->can('create', ShopUser::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9]{10}$/', new NepalMobilePhone],
            'role_id' => [
                'required',
                'integer',
                Rule::exists(Role::class, 'id')->where(fn ($query) => $query
                    ->where('shop_id', TenantContext::currentShop()->id)
                    ->where('name', '!=', 'Shop Owner')),
            ],
            'password' => ['required', 'string', 'min:8'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.unique' => __('staff.email_already_registered'),
            'role_id.exists' => __('staff.invalid_role'),
        ];
    }
}
