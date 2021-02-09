<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\Area;
use App\Models\AreaDisabledDay;
use App\Models\Reservation;
use App\Models\Unit;

class ReservationController extends Controller
{
    public function getReservations()
    {
        $array = ['error' => '', 'list' => []];
        $daysHelper = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

        $areas = Area::where('allowed', 1)->get();
        foreach($areas as $area) {
            $dayList = explode(',', $area['days']);

            $dayGroups = [];

            // Adicionar promeiro dia
            $lastDay = intval(current($dayList));
            $dayGroups[] =  $daysHelper[$lastDay];
            array_shift($dayList);

            // Adicionar dias relevantes
            foreach($dayList as $day) {
                if(intval($day) != $lastDay + 1) {
                    $dayGroups[] = $daysHelper[$lastDay];
                    $dayGroups[] = $daysHelper[$day];
                }

                $lastDay = intval($day);
            }

            // Adicionar último dia
            $dayGroups[] = $daysHelper[end($dayList)];

            // Juntanto Datas
            $dates = '';
            $close = 0;
            foreach($dayGroups as $group) {
                if($close === 0) {
                    $dates .= $group;
                } else {
                    $dates .= '-'.$group.',';
                }

                $close = 1 - $close;
            }

            $dates = explode(',', $dates);
            array_pop($dates);

            // Adicionar time
            $start = date('H:i', strtotime($area['start_time']));
            $end = date('H:i', strtotime($area['end_time']));

            foreach($dates as $dkey => $dValue) {
                $dates[$dkey] .= ' '.$start.' às '.$end;
            }

            $array['list'][] = [
                'id' => $area['id'],
                'cover' => asset('storage/'.$area['cover']),
                'title' => $area['title'],
                'dates' => $dates
            ];
        }

        return $array;
    }

    public function setReservation($id, Request $request)
    {
        $array = ['error' => ''];

        $validator = Validator::make($request->all(), [
            'date' => 'required|date_format:Y-m-d',
            'time' => 'required|date_format:H:i:s',
            'property' => 'required'
        ]);

        if(!$validator->fails()) {

            $date = $request->input('date');
            $time = $request->input('time');
            $property = $request->input('property');

            $unit = Unit::find($property);
            $area = Area::find($id);

            if($unit && $area) {
                $can = true;
                $weekDay = date('w', strtotime($date));

                // Verificar se está dentro do padrão
                $allowedDays = explode(',', $area['days']);
                if(!in_array($weekDay, $allowedDays)) {
                    $can = false;
                } else {
                    $start = strtotime($area['start_time']);
                    $end = strtotime('-1 hour', strtotime($area['end_time']));
                    $haveTime = strtotime($time);

                    if($haveTime < $start || $haveTime > $end) {
                        $can = false;
                    }
                }

                // Verificar se está dentro dos dias desabilitados
                $existingDisabledDay = AreaDisabledDay::where('id_area', $id)->where('day', $date)->count();
                if($existingDisabledDay > 0) {
                    $can = false;
                }

                // Verificar se não existe outra reserva no mesmo dia/hora
                $existingReservations = Reservation::where('id_area', $id)
                    ->where('reservation_date', $date.' '.$time)
                    ->count();

                if($existingReservations) {
                    $can = false;
                }

                if($can) {

                    $newReservation = new Reservation();
                    $newReservation->id_unit = $property;
                    $newReservation->id_area = $id;
                    $newReservation->reservation_date = $date.' '.$time;
                    $newReservation->save();

                } else {
                    $array['error'] = 'Reserva não permitida neste dia ou horário';
                    return $array;
                }

            } else {
                $array['error'] = 'Dados incorretos!';
                return $array;
            }

        } else {
            $array['error'] = $validator->errors()->first();
            return $array;
        }

        return $array;
    }

}