<?php

namespace App\Http\Controllers\JournalEntry;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JournalEntry\JournalEntry;   
use App\Http\Resources\JournalEntry\JournalEntryResource;
class JournalEntryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index( Request $request )
    {
        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'created_at');
        $sortDirection = $request->input('sortDirection', 'desc');

        $journalEntries = JournalEntry::with('transaction', 'lines')->search($request->query('search'))
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
        return inertia('JournalEntries/Index', [
            'journalEntries' => JournalEntryResource::collection($journalEntries),
        ]);

    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
