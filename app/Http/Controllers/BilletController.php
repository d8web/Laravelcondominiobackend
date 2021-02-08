<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

use App\Models\Billet;
use App\Models\Unit;

class BilletController extends Controller
{
    public function getAll(Request $request)
    {
        $array = ['error' => ''];

        $property = $request->input('property');
        if($property) {
            $user = Auth::user();

            $unit = Unit::where('id', $property)
                ->where('id_owner', $user['id'])
                ->count();
            
            if($unit > 0) {
                $billets = Billet::where('id_unit', $property)->get();

                foreach($billets as $billetKey => $billetValue) {
                    $billets[$billetKey]['fileurl'] = asset('storage/'.$billetValue['fileurl']);
                }

                $array['list'] = $billets;   
            } else {
                $array['error'] = 'Você não tem permissão para ver unidades de outros usuários!';
            }

        } else {
            $array['error'] = 'Propriedade não enviada!';
        }

        return $array;
    }   
}