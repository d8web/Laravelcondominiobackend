<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

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

    public function getDisabledDates($id)
    {
        $array = ['error' => '', 'list' => []];

        $area = Area::find($id);
        if($area) {

            $disabledDays = AreaDisabledDay::where('id_area', $id)->get();
            foreach($disabledDays as $disabledDay) {
                $array['list'][] = $disabledDay['day'];
            }

            $allowedDays = explode(',', $area['days']);
            $offDays = [];

            for($q=0;$q<7;$q++) {
                if(!in_array($q, $allowedDays)) {
                    $offDays[] = $q;
                }
            }

            // Listar os dias proibidos nos próximos três meses
            $start = time();
            $end = strtotime('+3 months');

            for(
                $current = $start;
                $current < $end;
                $current = strtotime('+1 day', $current)
            )
            {
                $wd = date('w', $current);
                if(in_array($wd, $offDays)) {
                    $array['list'][] = date('Y-m-d', $current);
                }
            }

        } else {
            $array['error'] = 'Área não existe!';
            return $array;
        }

        return $array;
    }

    public function getTimes($id, Request $request)
    {
      $array = ['error' => '', 'list' => []];
      
        $validator = Validator::make($request->all(), [
          'date' => 'required|date_format:Y-m-d'
        ]);

        if(!$validator->fails()) {

          $date = $request->input('date');
          $area = Area::find($id);

          if($area)
          {
            $can = true;

            // Verificar se o dia não é permitido
            $existingDisabledDay = AreaDisabledDay::where('id_area', $id)->where('day', $date)->count();
            if($existingDisabledDay > 0)
            {
              // Se $existingDisabledDay for maior que 0, significa que o dia na area $id está bloqueado.
              $can = false;
            }

            // Verificar se o dia é permitido
            $allowedDays = explode(',', $area['days']); // $area['days'] = 1,2,3,4
            $weekDay = date('w', strtotime($date));
            if(!in_array($weekDay, $allowedDays))
            {
              $can = false;
            }

            // Se can ainda for true, retornamos a lista de horários da data enviada em date
            if($can)
            {
              $start = strtotime($area['start_time']);
              $end = strtotime($area['end_time']);

              $times = [];
              // $lasttime = $start, enquanto $lasttime < $end, $lasttime + 1 hora.

              for($lastTime = $start; $lastTime < $end; $lastTime = strtotime('+1 hour', $lastTime))
              {
                // Retorna os horários da area $id na data $date no formato timestamps.
                $times[] = $lastTime;
              }

              $timeList = [];
              foreach($times as $time)
              {
                $timeList[] = [
                  'id' => date('H:i:s', $time), // data real retornada do timestamps $times
                  'title' => date('H:i', $time).' - '.date('H:i', strtotime('+ 1 hour', $time)) // hora - + 1hora
                ];
              }

              // Remover horários que tem reserva
              $reservations = Reservation::where('id_area', $id)->whereBetween('reservation_date', [
                $date.' 00:00:00',
                $date.' 23:59:59'
              ])->get();
              
              // Gerando as horas a serem removidas do array $timeList
              $toRemove = [];
              foreach($reservations as $reservation)
              {
                // Pegando a hora da reserva reservation_date e adicionando no array pra remover.
                $time = date('H:i:s', strtotime($reservation['reservation_date']));
                $toRemove[] = $time;
              }

              foreach($timeList as $timeItem)
              {
                // Verificar se no array $timeItem['id'] não possui a hora da reserva
                if(!in_array($timeItem['id'], $toRemove))
                {
                  $array['list'][] = $timeItem;
                }
              }

            }

          } 
          else {
            $array['error'] = 'Esta area não existe!';
            return $array;
          }

        }
        else {
          $array['error'] = $validator->errors()->first();
          return $array;
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

    public function getMyReservations(Request $request)
    {
      $array = ['error' => '', 'list' => []];

      $property = $request->input('property');
      if($property) {

        $unit = Unit::find($property);
        if($unit) {

          $reservations = Reservation::where('id_unit', $property)->orderBy('reservation_date', 'DESC')->get();
          foreach($reservations as $reservation)
          {
            $area = Area::find($reservation['id_area']);

            $daterev = date('d/m/Y H:i', strtotime($reservation['reservation_date']));
            $afterTime = date('H:i', strtotime('+1 hour', strtotime($reservation['reservation_date'])));

            $daterev .= ' às '.$afterTime;

            $array['list'][] = [
              'id' => $reservation['id'],
              'id_area' => $reservation['id_area'],
              'title' => $area['title'],
              'cover' => asset('storage/'.$area['cover']),
              'date_reserved' => $daterev
            ];
          }

        } else {
          $array['error'] = 'Propriedade não existe';
          return $array;
        }

      } else {
        $array['error'] = 'Propriedade não enviada!';
        return $array;
      }

      return $array;
    }

    public function delMyReservation($id)
    {
      $array = ['error' => ''];

      $user = Auth::user();
      $reservation = Reservation::find($id);

      if($reservation)
      {

        $unit = Unit::where('id', $reservation['id_unit'])->where('id_owner', $user['id'])->count();
        if($unit > 0) {

            Reservation::find($id)->delete();

        } else {
          $array['error'] = 'Esta reserva não é sua.';
          return $array;
        }

      } else {
        $array['error'] = 'Reserva não existe.';
        return $array;
      }

      return $array;
    }
}