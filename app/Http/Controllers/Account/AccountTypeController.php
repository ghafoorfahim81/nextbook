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
            'items' => AccountTypeResource::collection($accountTypes),
        ]);
    }

    public function create(Request $request): Response
    {
        return view('accountType.create');
    }

    public function store(AccountTypeStoreRequest $request): Response
    {
        $accountType = AccountType::create($request->validated());

        $request->session()->flash('accountType.id', $accountType->id);

        return redirect()->route('accountTypes.index');
    }

    public function show(Request $request, AccountType $accountType): Response
    {
        return view('accountType.show', compact('accountType'));
    }

    public function edit(Request $request, AccountType $accountType): Response
    {
        return view('accountType.edit', compact('accountType'));
    }

    public function update(AccountTypeUpdateRequest $request, AccountType $accountType): Response
    {
        $accountType->update($request->validated());

        $request->session()->flash('accountType.id', $accountType->id);

        return redirect()->route('accountTypes.index');
    }

    public function destroy(Request $request, AccountType $accountType): Response
    {
        $accountType->delete();

        return redirect()->route('accountTypes.index');
    }
}
