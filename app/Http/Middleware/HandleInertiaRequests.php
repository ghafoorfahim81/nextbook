<?php

namespace App\Http\Middleware;

use App\Http\Resources\Administration\CategoryResource;
use App\Models\Administration\Category; 
use App\Http\Resources\Account\AccountResource;
use App\Models\Account\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $cacheDuration = 60 * 60;



        // $items = Cache::remember('items_'.app()->getLocale(), $cacheDuration,
        //     fn () => ItemResource::collection(
        //         Item::latest()->take(10)->get()
        //     ));

        // $measures = Cache::remember('measures_'.app()->getLocale(), $cacheDuration,
        //     fn () => MeasureResource::collection(
        //         Measure::latest()->take(10)->get()
        //     ));

        $categories = Cache::remember('categories', $cacheDuration,
            fn () => CategoryResource::collection(
                Category::latest()->take(10)->get()
            ));
        
        $accounts = Cache::remember('accounts', $cacheDuration,
            fn () => AccountResource::collection(
                Account::latest()->take(10)->get()
            ));
  

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'categories' => $categories,
            'accounts' => $accounts,
        ];

    }
}
