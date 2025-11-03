<?php

namespace App\Http\Controllers;

use App\Models\UserDetail;
use Illuminate\Http\Request;

class UserDetailController extends Controller
{
    // 🟢 CREATE
    public function store(Request $req)
    {
        $data = $req->validate([
            'device_token' => 'required|string|unique:user_details,device_token',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'lat' => 'nullable|numeric',
            'lon' => 'nullable|numeric',
        ]);

        $user = UserDetail::create($data);

        return response()->json(['message' => 'User added', 'data' => $user]);
    }

    // 🟡 READ (all users)
    public function index()
    {
        return response()->json(UserDetail::all());
    }

    // 🔵 UPDATE by id
    public function update(Request $req, $id)
    {
        $data = $req->validate([
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'lat' => 'nullable|numeric',
            'lon' => 'nullable|numeric',
        ]);

        $user = UserDetail::findOrFail($id);
        $user->update($data);

        return response()->json(['message' => 'Updated successfully', 'data' => $user]);
    }

    // 🔴 DELETE
    public function destroy($id)
    {
        $user = UserDetail::findOrFail($id);
        $user->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }
}
