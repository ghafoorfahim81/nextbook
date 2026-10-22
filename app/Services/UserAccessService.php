<?php

namespace App\Services;

use App\Models\Administration\Branch;
use App\Models\Administration\Company;
use App\Models\Administration\UserBranchAccess;
use App\Models\Administration\UserCompanyAccess;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Which companies and branches a user may act in (phase 5).
 *
 * The one place these rules live, so the middleware that resolves a request's
 * company and branch, the switchers and the access screens cannot disagree:
 *
 *  - A user's home company (users.company_id) is always accessible, and so is
 *    their home branch within it. Grants in user_company_access and
 *    user_branch_access add to that; they never take the home away.
 *  - A super-admin may act in every branch of a company they have access to,
 *    as they could switch to any branch of their company before. Access to the
 *    company itself still has to be granted.
 *  - Anyone else acts only in the branches named for them.
 *
 * Soft-deleted companies and branches are never accessible.
 *
 * Every id handed in here is checked, never trusted: these methods are what
 * stand between a posted company_id and another company's books.
 */
class UserAccessService
{
    /**
     * Every company the user may act in, home company first.
     *
     * @return array<int, string>
     */
    public function accessibleCompanyIds(User $user): array
    {
        $granted = UserCompanyAccess::query()
            ->where('user_id', $user->id)
            ->pluck('company_id')
            ->all();

        $ids = array_values(array_unique(array_filter([
            $user->company_id ? (string) $user->company_id : null,
            ...array_map('strval', $granted),
        ])));

        if ($ids === []) {
            return [];
        }

        // Keep the order above, drop anything deleted since it was granted.
        $live = Company::query()->whereKey($ids)->pluck('id')->map(fn ($id) => (string) $id)->all();

        return array_values(array_intersect($ids, $live));
    }

    /**
     * Whether the user belongs to a company, deleted or not: their home
     * company, or one granted to them.
     *
     * For the admin screens that act on companies themselves — restoring a
     * deleted company is something its own members may do, and nobody else.
     * Acting *in* a company goes through canAccessCompany(), which also
     * requires it to be live.
     */
    public function isMemberOf(User $user, ?string $companyId): bool
    {
        if (! $companyId) {
            return false;
        }

        if ((string) $user->company_id === $companyId) {
            return true;
        }

        return UserCompanyAccess::query()
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->exists();
    }

    /**
     * Ids of the users who belong to a company: those whose home it is, and
     * those granted it. The user list and every user screen are bounded by
     * this, since users carry no company scope of their own.
     *
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    public function membersOf(string $companyId)
    {
        return User::query()->where(fn ($query) => $query
            ->where('company_id', $companyId)
            ->orWhereIn('id', UserCompanyAccess::query()
                ->select('user_id')
                ->where('company_id', $companyId)));
    }

    public function canAccessCompany(User $user, ?string $companyId): bool
    {
        return $companyId !== null
            && $companyId !== ''
            && in_array($companyId, $this->accessibleCompanyIds($user), true);
    }

    /**
     * The branches of one company the user may act in.
     *
     * @return array<int, string>
     */
    public function allowedBranchIds(User $user, ?string $companyId): array
    {
        if (! $this->canAccessCompany($user, $companyId)) {
            return [];
        }

        $branches = Branch::query()
            ->withoutGlobalScope('companySpecific')
            ->where('company_id', $companyId);

        if (! $user->isSuperAdmin()) {
            $granted = UserBranchAccess::query()
                ->where('user_id', $user->id)
                ->where('company_id', $companyId)
                ->pluck('branch_id')
                ->map(fn ($id) => (string) $id)
                ->all();

            $branches->whereKey(array_filter([
                $user->branch_id ? (string) $user->branch_id : null,
                ...$granted,
            ]));
        }

        return $branches
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public function canAccessBranch(User $user, ?string $companyId, ?string $branchId): bool
    {
        return $branchId !== null
            && in_array($branchId, $this->allowedBranchIds($user, $companyId), true);
    }

    /**
     * The branch a request lands in when it has not picked one: the user's home
     * branch when it is in this company, else the company's main branch, else
     * the first branch they may use. Null when they may use none.
     */
    public function defaultBranchId(User $user, ?string $companyId): ?string
    {
        $allowed = $this->allowedBranchIds($user, $companyId);

        if ($user->branch_id && in_array((string) $user->branch_id, $allowed, true)) {
            return (string) $user->branch_id;
        }

        // allowedBranchIds() orders the main branch first.
        return $allowed[0] ?? null;
    }

    /**
     * What the switchers in the top bar offer: the user's companies, and the
     * branches they may use in the one they are acting in.
     *
     * The plan keeps the words "company" and "branch" away from a business that
     * has one of each, so the layout shows a switcher only when its list has a
     * second entry — which is why both lists are always sent, even when short.
     *
     * @return array{companies: array<int, array{id: string, name: string}>, branches: array<int, array{id: string, name: string}>}
     */
    public function switcherOptions(User $user, ?string $activeCompanyId): array
    {
        $companyIds = $this->accessibleCompanyIds($user);

        $companies = Company::query()->whereKey($companyIds)->get()
            ->sortBy(fn (Company $company) => array_search((string) $company->id, $companyIds, true))
            ->map(fn (Company $company) => ['id' => (string) $company->id, 'name' => $company->localizedName()])
            ->values()
            ->all();

        $branchIds = $this->allowedBranchIds($user, $activeCompanyId);

        $branches = Branch::query()
            ->withoutGlobalScope('companySpecific')
            ->whereKey($branchIds)
            ->get(['id', 'name'])
            ->sortBy(fn (Branch $branch) => array_search((string) $branch->id, $branchIds, true))
            ->map(fn (Branch $branch) => ['id' => (string) $branch->id, 'name' => (string) $branch->name])
            ->values()
            ->all();

        return ['companies' => $companies, 'branches' => $branches];
    }

    /**
     * Set a user's grants to exactly these, within the companies the actor
     * belongs to — the access screen's save.
     *
     * Grants in companies the actor cannot see are left alone: a super-admin
     * of one company must not be able to take another company away from a user
     * by leaving it unticked on a screen that never showed it. The user's home
     * company is never removed; it is membership, not a grant. A branch is kept
     * only under a company the user ends up with.
     *
     * @param  array<int, string>  $companyIds
     * @param  array<int, string>  $branchIds
     */
    public function syncAccess(User $user, array $companyIds, array $branchIds, User $actor): void
    {
        $scope = $this->accessibleCompanyIds($actor);
        $home = $user->company_id ? (string) $user->company_id : null;

        $wanted = array_values(array_intersect(array_map('strval', $companyIds), $scope));
        if ($home && in_array($home, $scope, true) && ! in_array($home, $wanted, true)) {
            $wanted[] = $home;
        }

        $branches = Branch::query()
            ->withoutGlobalScope('companySpecific')
            ->whereKey(array_map('strval', $branchIds))
            ->whereIn('company_id', $wanted)
            ->get(['id', 'company_id']);

        DB::transaction(function () use ($user, $actor, $scope, $wanted, $branches): void {
            UserBranchAccess::query()
                ->where('user_id', $user->id)
                ->whereIn('company_id', $scope)
                ->whereNotIn('branch_id', $branches->pluck('id')->all())
                ->delete();

            UserCompanyAccess::query()
                ->where('user_id', $user->id)
                ->whereIn('company_id', $scope)
                ->whereNotIn('company_id', $wanted)
                ->delete();

            foreach ($wanted as $companyId) {
                // The home company needs no grant of its own, but a branch
                // grant hangs off one (the foreign key), so it gets one when it
                // has branches granted.
                if ($companyId !== (string) $user->company_id || $branches->contains('company_id', $companyId)) {
                    $this->grantCompany($user, $companyId, $actor->id);
                }
            }

            foreach ($branches as $branch) {
                $this->grantBranch($user, $branch, $actor->id);
            }
        });
    }

    /**
     * Give a user a company. Idempotent.
     */
    public function grantCompany(User $user, string $companyId, ?string $actorId = null): void
    {
        UserCompanyAccess::query()->firstOrCreate(
            ['user_id' => $user->id, 'company_id' => $companyId],
            ['created_by' => $actorId],
        );
    }

    /**
     * Give a user one branch, and the company above it if they lack it.
     * Idempotent.
     */
    public function grantBranch(User $user, Branch $branch, ?string $actorId = null): void
    {
        $this->grantCompany($user, (string) $branch->company_id, $actorId);

        UserBranchAccess::query()->firstOrCreate(
            ['user_id' => $user->id, 'branch_id' => $branch->id],
            ['company_id' => $branch->company_id, 'created_by' => $actorId],
        );
    }
}
