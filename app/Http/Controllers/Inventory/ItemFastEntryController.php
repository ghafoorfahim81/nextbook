<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ItemFastEntryController extends Controller
{

    public function create()
    {
        return inertia('Inventories/Items/FasEntry'); // no “t”
    }


    public function store(Request $request)
    {
        //
    }
}
