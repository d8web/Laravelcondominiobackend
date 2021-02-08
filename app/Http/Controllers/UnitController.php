<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Unit;
use App\Models\UnitPeople;
use App\Models\UnitVehicle;
use App\Models\UnitPet;

class UnitController extends Controller
{
    public function getInfo($id)
    {
        $array = ['error' => ''];

        $unit = Unit::find($id);
        if($unit) {
            
            $peoples = UnitPeople::where('id_unit', $id)->get();
            foreach($peoples as $peopleKey => $peopleValue) {
                // Ajeitar birthdate
                $peoples[$peopleKey]['birthdate'] = date('d/m/Y', strtotime($peopleValue['birthdate']));
            }

            $vehicles = UnitVehicle::where('id_unit', $id)->get();
            $pets = UnitPet::where('id_unit', $id)->get();

            $array['peoples'] = $peoples;
            $array['vehicles'] = $vehicles;
            $array['pets'] = $pets;

        } else {
            $array['error'] = 'Unidade não encontrada!';
        }

        return $array;
    }
}
