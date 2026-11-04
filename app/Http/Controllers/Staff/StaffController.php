<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Actions\Staff\AddStaffMember;
use App\Actions\Staff\ChangeStaffRole;
use App\Actions\Staff\QueryStaffMembers;
use App\Actions\Staff\RemoveStaffMember;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreStaffMemberRequest;
use App\Http\Requests\Staff\UpdateStaffRoleRequest;
use App\Models\ShopUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

final class StaffController extends Controller
{
    public function index(QueryStaffMembers $queryStaffMembers): View
    {
        $this->authorize('viewAny', ShopUser::class);

        $staffMembers = $queryStaffMembers->execute();

        return view('staff.index', compact('staffMembers'));
    }

    public function create(QueryStaffMembers $queryStaffMembers): View
    {
        $this->authorize('create', ShopUser::class);
        $roles = $queryStaffMembers->availableRoles();

        return view('staff.form', compact('roles'));
    }

    public function store(StoreStaffMemberRequest $request, AddStaffMember $addStaffMember): RedirectResponse
    {
        try {
            $addStaffMember->execute($request->validated(), (int) $request->user()->id);

            return redirect()->route('staff.index')->with('status', __('staff.created_success'));
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', __('common.error_generic'));
        }
    }

    public function edit(ShopUser $shopUser, QueryStaffMembers $queryStaffMembers): View
    {
        $this->authorize('update', $shopUser);
        $shopUser->load(['user', 'role']);
        $roles = $queryStaffMembers->availableRoles();

        return view('staff.form', [
            'staffMember' => $shopUser,
            'roles' => $roles,
        ]);
    }

    public function update(UpdateStaffRoleRequest $request, ShopUser $shopUser, ChangeStaffRole $changeStaffRole): RedirectResponse
    {
        $this->authorize('update', $shopUser);

        try {
            $changeStaffRole->execute($shopUser, (int) $request->validated('role_id'), (int) $request->user()->id);

            return redirect()->route('staff.index')->with('status', __('staff.role_updated_success'));
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', __('common.error_generic'));
        }
    }

    public function destroy(ShopUser $shopUser, RemoveStaffMember $removeStaffMember): RedirectResponse
    {
        $this->authorize('delete', $shopUser);

        try {
            $removeStaffMember->execute($shopUser, (int) auth()->id());

            return redirect()->route('staff.index')->with('status', __('staff.removed_success'));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('common.error_generic'));
        }
    }
}
