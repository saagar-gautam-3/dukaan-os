<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use App\Models\Role;
use App\Models\ShopUser;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateStaffRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $shopUser = $this->route('shopUser');

        return $shopUser instanceof ShopUser && ($this->user()?->can('update', $shopUser) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'role_id' => [
                'required',
                'integer',
                Rule::exists(Role::class, 'id')->where(fn ($query) => $query
                    ->where('shop_id', TenantContext::currentShop()->id)
                    ->where('name', '!=', 'Shop Owner')),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['role_id.exists' => __('staff.invalid_role')];
    }
}
