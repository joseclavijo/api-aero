<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class IndexController extends Controller
{
    public function chamaApi($method, $url, $data = false)
    {
        $curl = curl_init();

        switch ($method)
        {
            case "POST":
                curl_setopt($curl, CURLOPT_POST, 1);

                if ($data)
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                break;
            case "PUT":
                curl_setopt($curl, CURLOPT_PUT, 1);
                break;
            default:
                if ($data)
                    $url = sprintf("%s?%s", $url, http_build_query($data));
        }

        // Optional Authentication:
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_USERPWD, "username:password");

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($curl);

        curl_close($curl);

        return $result;
    }

    public function show()
    {
        $url = 'http://prova.123milhas.net/api/flights';

        $dados = $this->chamaApi('GET', $url);

        $dados = json_decode($dados);

        // duas tarifas

        $tarifas = $this->buscaTarifas($dados);

        // 3 companhias areas

        $cias = $this->buscaCia($dados);

        $voos_ida = array();

        $voos_volta = array();

        for ($i=0; $i < count($cias); $i++) {
            for($j=0; $j < count($tarifas); $j++) {
                $voos_ida[$cias[$i]][$tarifas[$j]] = $this->buscarQuantidadeIdaOuVolta($tarifas[$j], $dados, $cias[$i], true);
                $voos_volta[$cias[$i]][$tarifas[$j]] = $this->buscarQuantidadeIdaOuVolta($tarifas[$j], $dados, $cias[$i], false);
            }
        }

        //dd($voos_ida, $voos_volta);

        $grupos_gol = $this->criarGrupos($voos_ida['GOL']['1AF'], $voos_volta['GOL']['1AF']);
        $grupos_azul = $this->criarGrupos($voos_ida['AZUL']['1AF'], $voos_volta['AZUL']['1AF']);
        $grupos_latam = $this->criarGrupos($voos_ida['LATAM']['1AF'], $voos_volta['LATAM']['1AF']);
        $grupos_mixta = $this->criarGrupos($voos_ida['AZUL']['4DA'], $voos_volta['LATAM']['4DA']);


        $grupos = array();

        foreach ($grupos_gol as $valor) {
            array_push($grupos, $valor);
        }

        foreach ($grupos_azul as $valor) {
            array_push($grupos, $valor);
        }

        foreach ($grupos_latam as $valor) {
            array_push($grupos, $valor);
        }

        foreach ($grupos_mixta as $valor) {
            array_push($grupos, $valor);
        }

        for ($i=0; $i<count($grupos); $i++) {
            $grupos[$i]['uniqueId'] = $i;
        }

        $menor = '';
        $id = '';

        foreach ($grupos as $valor){

            if ($menor == '') {
                $menor = $valor['totalPrice'];
                $id = $valor['uniqueId'];
            }

            if ($valor['totalPrice'] < $menor) {
                $menor = $valor['totalPrice'];
                $id = $valor['uniqueId'];
            }
        }

        $resultado['flights'] = $dados;
        $resultado['groups'] = $grupos;
        $resultado['totalGroups'] = count($grupos);
        $resultado['totalFlights'] = count($dados);
        $resultado['cheapestPrice'] = $menor;
        $resultado['cheapestGroup'] = $id;


        return response()->json($resultado,  200);
        
    }

    public function criarGrupos($voos_ida, $voos_volta)
    {
        $grupo = array();
        $grupo_pai = array();
        $cont = 0;

        foreach ($voos_ida as $ida) {
            foreach ($voos_volta as $volta) {
                $grupo['uniqueId'] = $cont;
                $grupo['totalPrice'] = $ida->price + $volta->price;
                $grupo['outbound'] = $ida;
                $grupo['inbound'] = $volta;

                array_push($grupo_pai, $grupo);
            }
            $cont += 1;
        }

        return $grupo_pai;
    }

    public function buscaTarifas($dados)
    {
        $grupos = [];

        for ($i=0; $i<count($dados); $i++) {
            array_push($grupos, $dados[$i]->fare);
        }

        $grupos = array_unique($grupos);

        $grupos2 = array();

        foreach ($grupos as $valor) {
            array_push($grupos2, $valor);
        }

        return $grupos2;
    }

    public function buscaCia($dados)
    {
        $grupos = [];

        for ($i=0; $i<count($dados); $i++) {
            array_push($grupos, $dados[$i]->cia);
        }

        return array_unique($grupos);
    }

    public function buscarQuantidadeIdaOuVolta($tarifa, $dados, $cia,  $outbound=false)
    {
        $resultado = array();

        for ($i=0; $i<count($dados); $i++) {
            if ($dados[$i]->fare == $tarifa) {
                if ($outbound) {
                    if ($dados[$i]->outbound == 1 && $dados[$i]->cia == $cia) {
                        array_push($resultado, $dados[$i]);
                    }
                } else {
                    if ($dados[$i]->inbound == 1 && $dados[$i]->cia == $cia) {
                        array_push($resultado, $dados[$i]);
                    }
                }
            }
        }

        return $resultado;

    }
}
