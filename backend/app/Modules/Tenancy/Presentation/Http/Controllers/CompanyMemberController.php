<?php

namespace App\Modules\Tenancy\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Application\UseCases\RemoveCompanyMember;
use App\Modules\Tenancy\Application\UseCases\UpdateCompanyMemberRole;
use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use App\Modules\Tenancy\Presentation\Requests\UpdateCompanyMemberRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CompanyMemberController extends Controller
{
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
                : null
        );

        $updated = $companyModel->memberships()
            ->whereKey($member)
            ->firstOrFail();

        return response()->json([
            'data' => $updated->only(['id', 'name', 'email'])
                + [
                    'role' => $updated->pivot->role,
                    'is_active' => (bool) $updated->pivot->is_active,
                ],
        ]);
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
