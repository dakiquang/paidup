<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return CustomerResource::collection($request->user()->customers()->orderBy('name')->paginate(50));
    }

    public function store(Request $request): CustomerResource
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required', 'email', 'max:190',
                Rule::unique('customers')->where('user_id', $request->user()->id),
            ],
        ]);

        return new CustomerResource($request->user()->customers()->create($data));
    }
}
