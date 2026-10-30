<?php

namespace App\Modules\Tenancy\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Application\UseCases\RemoveCompanyMember;
use App\Modules\Tenancy\Application\UseCases\UpdateCompanyMemberRole;
use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use App\Modules\Tenancy\Presentation\Requests\UpdateCompanyMemberRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class CompanyMemberController extends Controller
{
    public function store(Request $request, int $company): JsonResponse
    {
        $email = strtolower(trim((string) $request->input('email')));
        $existingUser = \App\Models\User::query()->where('email', $email)->first();
        $rules = [
            'email' => ['required', 'email', 'max:254'],
            'role' => ['required', 'string', 'in:manager,member'],
        ];
        if (! $existingUser) {
            $rules += [
                'name' => ['required', 'string', 'max:120'],
                'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
            ];
        }
        $data = $request->validate($rules);
        $companyModel = Company::query()->findOrFail($company);
        $user = DB::transaction(function () use ($data, $email, $existingUser, $companyModel) {
            $user = $existingUser ?? \App\Models\User::query()->create([
                'name' => trim($data['name']),
                'email' => $email,
                'password' => $data['password'],
            ]);
            if ($companyModel->memberships()->whereKey($user->id)->exists()) {
                throw ValidationException::withMessages([
                    'email' => ['La persona ya pertenece a esta empresa. Podés cambiar su rol en la lista de miembros.'],
                ]);
            }
            $companyModel->memberships()->attach($user->id, [
                'role' => $data['role'], 'is_active' => true,
            ]);
            return $user;
        });
        $member = $companyModel->memberships()->whereKey($user->id)->firstOrFail();
        return response()->json(['data' => [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'role' => $member->pivot->role,
            'is_active' => (bool) $member->pivot->is_active,
            'permissions' => $this->defaultPermissions($member->pivot->role),
        ], 'account_created' => ! $existingUser], 201);
    }

    public function index(int $company): JsonResponse
    {
        $members = Company::query()->findOrFail($company)
            ->memberships()
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.email']);

        return response()->json([
            'data' => $members->map(fn ($member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->pivot->role,
                'is_active' => (bool) $member->pivot->is_active,
                'permissions' => $this->permissions($member->pivot->permissions, $member->pivot->role),
            ]),
        ]);
    }

    public function update(
        UpdateCompanyMemberRequest $request,
        int $company,
        int $member,
        UpdateCompanyMemberRole $updateRole
    ): Response {
        $companyModel = Company::query()->findOrFail($company);
        $updateRole->execute(
            $companyModel,
            $member,
            $request->validated('role'),
            $request->has('is_active')
                ? (bool) $request->validated('is_active')
                : null,
            $request->validated('permissions')
        );

        $updated = $companyModel->memberships()
            ->whereKey($member)
            ->firstOrFail();

        return response()->json([
            'data' => $updated->only(['id', 'name', 'email'])
                + [
                    'role' => $updated->pivot->role,
                    'is_active' => (bool) $updated->pivot->is_active,
                    'permissions' => $this->permissions($updated->pivot->permissions, $updated->pivot->role),
                ],
        ]);
    }

    private function defaultPermissions(string $role): array
    {
        return $role === 'owner' ? ['*'] : ($role === 'manager'
            ? ['catalog.view', 'catalog.manage', 'inventory.view', 'inventory.manage', 'sales.operate', 'sales.manage', 'reports.view', 'activity.view']
            : ['catalog.view', 'inventory.view', 'sales.operate']);
    }

    private function permissions(mixed $permissions, string $role): array
    {
        if (is_string($permissions)) {
            $decoded = json_decode($permissions, true);
            if (is_array($decoded)) return $decoded;
        }
        return is_array($permissions) ? $permissions : $this->defaultPermissions($role);
    }

    public function destroy(
        int $company,
        int $member,
        RemoveCompanyMember $removeMember
    ): Response {
        $removeMember->execute(
            Company::query()->findOrFail($company),
            $member
        );

        return response()->noContent();
    }
}
