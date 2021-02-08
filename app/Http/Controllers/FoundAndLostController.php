<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

use App\Models\FoundAndLost;

class FoundAndLostController extends Controller
{
    public function getAll()
    {
        $array = ['error' => ''];

        $lost = FoundAndLost::where('status', 'LOST')
            ->orderBy('datecreated', 'DESC')
            ->orderBy('id', 'DESC')
            ->get();

        $recovered = FoundAndLost::where('status', 'RECOVERED')
            ->orderBy('datecreated', 'DESC')
            ->orderBy('id', 'DESC')
            ->get();

        foreach($lost as $lostKey => $lostValue) {
            // Data padrão Brasil
            $lost[$lostKey]['datecreated'] = date('d/m/Y', strtotime($lostValue['datecreated']));
            // Montando url da photo
            $lost[$lostKey]['photo'] = asset('storage/'.$lostValue['photo']);
        }

        foreach($recovered as $recKey => $recValue) {
            // Data padrão Brasil
            $recovered[$recKey]['datecreated'] = date('d/m/Y', strtotime($recValue['datecreated']));
            // Montando url da photo
            $recovered[$recKey]['photo'] = asset('storage/'.$recValue['photo']);
        }

        $array['lost'] = $lost;
        $array['recovered'] = $recovered;

        return $array;
    }

    public function insert(Request $request)
    {
        $array = ['error' => ''];

        $validator = Validator::make($request->all(), [
            'description' => 'required',
            'where' => 'required',
            'photo' => 'required|file|mimes:jpg,png'
        ]);

        if(!$validator->fails()) {

            $description = $request->input('description');
            $where = $request->input('where');
            // pegando foto e já salvando na pasta public[StoreFacade]
            $file = $request->file('photo')->store('public');
            $file = explode('public/', $file);

            $photo = $file[1];

            $newLost = new FoundAndLost();
            $newLost->status = strtoupper('LOST');
            $newLost->photo = $photo;
            $newLost->description = $description;
            $newLost->where = $where;
            $newLost->datecreated = date('Y-m-d');
            $newLost->save();


        } else {
            $array['error'] = $validator->errors()->first();
        }

        return $array;
    }

    public function update($id, Request $request)
    {
        $array = ['error' => ''];

        $status = $request->input('status');

        if($status && in_array($status, ['lost', 'recovered'])) {

            $item = FoundAndLost::find($id);
            if($item) {
                $item->status = $status;
                $item->save();
            } else {
                $array['error'] = 'Produto inexistente';
            }

        } else {
            $array['error'] = 'Status não existe';
        }

        return $array;
    }

}