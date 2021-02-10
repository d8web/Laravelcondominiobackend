<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

use App\Models\User;

class UserController extends Controller
{
    public function updateUser(Request $request)
    {
        $array = ['error' => ''];

        $user = Auth::user();
        $newUser = User::find($user['id']);

        $name = $request->input('name');
        $email = $request->input('email');
        $cpf = $request->input('cpf');
        $password = $request->input('password');

        if($name) {
            $newUser->name = $name;
        }

        if($email) {
            $newUser->email = $email;
        }

        if($cpf) {
            $newUser->cpf = $cpf;
        }

        if($password) {
            $newUser->password = password_hash($password, PASSWORD_DEFAULT);
        }

        $newUser->save();

        $array['user'] = $newUser;

        return $array;
    }
}