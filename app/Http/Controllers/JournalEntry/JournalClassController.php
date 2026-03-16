<?php

namespace App\Http\Controllers\JournalEntry;

use App\Http\Controllers\Controller;
use App\Http\Requests\JournalEntry\JournalClassStoreRequest;
use App\Http\Requests\JournalEntry\JournalClassUpdateRequest; 
use App\Http\Resources\JournalEntry\JournalClassResource;
use App\Models\JournalEntry\JournalClass;
use Illuminate\Http\Request;

class JournalClassController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(JournalClass::class, 'journalClass');
    }
    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'created_at');
        $sortDirection = $request->input('sortDirection', 'desc');

        $journalClasses = JournalClass::search($request->query('search'))
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('JournalEntry/JournalClasses/Index', [
            'journalClasses' => JournalClassResource::collection($journalClasses),
        ]);
    }

    public function store(JournalClassStoreRequest $request)
    {
        $journalClass = JournalClass::create($request->validated());

        return redirect()->route('journal-classes.index')->with('success', __('general.created_successfully', ['resource' => __('sidebar.journal_entry.journal_class')]));
    }

    public function show(Request $request, JournalClass $journalClass)
    {
        return response()->json([
            'data' => new JournalClassResource($journalClass),
        ]);
    }

    public function update(JournalClassUpdateRequest $request, JournalClass $journalClass)
    {
        $journalClass->update($request->validated());
        return redirect()->back()->with('success', __('general.updated_successfully', ['resource' => __('sidebar.journal_entry.journal_class')]));
    }

    public function destroy(Request $request, JournalClass $journalClass)
    {
        $journalClass->delete();

        return redirect()->route('journal-types.index')->with('success', __('general.deleted_successfully', ['resource' => __('sidebar.journal_entry.journal_type')]));
    }
}
