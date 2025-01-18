<?php

namespace App\Http\Controllers\Api\Users;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;


class CategoryControllerUser extends Controller
{
    public function index()
    {
        $categories = Category::all();

        return [
            'status' => true,
            'message' => 'Data Category',
            'categories' => $categories,
        ];
    }


    public function show($id)
    {
        $category = Category::findOrFail($id);
        return response()->json([
            'status' => true,
            'message' => 'Data Detail Category',
            'data' => $category,
        ]);
    }

    


   
    
}
