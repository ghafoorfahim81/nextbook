<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ItemFastEntryController extends Controller
{

    public function create()
    {
        return view('Inventories.FastEntry');
    }

    public function store(Request $request)
    {
        //
    }
}
