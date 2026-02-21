<?php

namespace App\Http\Controllers\JournalEntry;

use App\Http\Controllers\Controller;
use App\Http\Requests\JournalEntry\JournalTypeStoreRequest;
use App\Http\Requests\JournalEntry\JournalTypeUpdateRequest;
use App\Http\Resources\JournalEntry\JournalTypeCollection;
use App\Http\Resources\JournalEntry\JournalTypeResource;
use App\Models\JournalEntry\JournalType;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class JournalTypeController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'created_at');
        $sortDirection = $request->input('sortDirection', 'desc');

        $journalTypes = JournalType::search($request->query('search'))
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('JournalEntry/JournalTypes/Index', [
            'journalTypes' => JournalTypeResource::collection($journalTypes),
        ]);
    }

    public function store(JournalTypeStoreRequest $request)
    {
        $journalType = JournalType::create($request->validated());

        return redirect()->route('journal-types.index')->with('success', __('general.created_successfully', ['resource' => __('sidebar.journal_entry.journal_type')]));
    }

    public function show(Request $request, JournalType $journalType)
    {
        return response()->json([
            'data' => new JournalTypeResource($journalType),
        ]);
    }

    public function update(JournalTypeUpdateRequest $request, JournalType $journalType)
    {
        $journalType->update($request->validated());
        return redirect()->back()->with('success', __('general.updated_successfully', ['resource' => __('sidebar.journal_entry.journal_type')]));
    }

    public function destroy(Request $request, JournalType $journalType)
    {
        $journalType->delete();

        return redirect()->route('journal-types.index')->with('success', __('general.deleted_successfully', ['resource' => __('sidebar.journal_entry.journal_type')]));
    }
}
