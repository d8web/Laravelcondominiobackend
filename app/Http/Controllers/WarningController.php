<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;

use Illuminate\Http\Request;
use App\Models\Warning;
use App\Models\Unit;

class WarningController extends Controller
{
    public function getMyWarnings(Request $request)
    {
        $array = ['error' => ''];

        $property = $request->input('property');
        if($property) {

            $user = Auth::user();

            $unit = Unit::where('id', $property)
                ->where('id_owner', $user['id'])
                ->count();
            
            if($unit > 0) {

                $warnings = Warning::where('id_unit', $property)
                    ->orderBy('datecreated', 'DESC')
                    ->orderBy('id', 'DESC')
                    ->get();

                foreach ($warnings as $warnKey => $warnValue) {
                    $warnings[$warnKey]['datecreated'] = date('d/m/Y', strtotime($warnValue['datecreated']));

                    $photolist = [];
                    $photos = explode(',', $warnValue['photos']);

                    foreach($photos as $photo) {
                        if(!empty($photo)) {
                            $photolist[] = asset('storage/'.$photo);
                        }
                    }

                    $warnings[$warnKey]['photos'] = $photolist;
                }

                $array['list'] = $warnings;

            } else {
                $array['error'] = 'Você não tem permissão para ver unidades de outros usuários!';
            }

        } else {
            $array['error'] = 'Propriedade não enviada!';
        }

        return $array;
    }
}
