<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\AccountTypeStoreRequest;
use App\Http\Requests\Account\AccountTypeUpdateRequest;
use App\Http\Resources\Account\AccountTypeResource;
use App\Models\Account\AccountType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountTypeController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'asc');

        $accountTypes = AccountType::search($request->query('search'))
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
        return inertia('Accounts/AccountTypes/Index', [
            'accountTypes' => AccountTypeResource::collection($accountTypes),
        ]);
    }

    public function create(Request $request)
    {
        return view('accountType.create');
    }

    public function store(AccountTypeStoreRequest $request)
    {
        $validated = $request->validated();
        $validated['slug'] = \Str::slug($validated['name']);
        $accountType = AccountType::create($validated);
        return redirect()->route('account-types.index');
    }

    public function show(Request $request, AccountType $accountType): Response
    {
        return view('accountType.show', compact('accountType'));
    }

    public function edit(Request $request, AccountType $accountType): Response
    {
        return view('accountType.edit', compact('accountType'));
    }

    public function update(AccountTypeUpdateRequest $request, AccountType $accountType)
    {
        $validated = $request->validated();
        $validated['slug'] = \Str::slug($validated['name']);
        $accountType->update($validated);
        return redirect()->route('account-types.index')->with('success', 'Account type created successfully.');
    }

    public function destroy(Request $request, AccountType $accountType)
    {
        if ($accountType->accounts()->count() > 0) {
            return redirect()->route('account-types.index')->with('error', 'Account type cannot be deleted because it is used in accounts.');
        }
        $accountType->delete();

        return redirect()->route('account-types.index');
    }
    public function restore(Request $request, AccountType $accountType)
    {
        $accountType->restore();
        return redirect()->route('account-types.index')->with('success', 'Account type restored successfully.');
    }
}
