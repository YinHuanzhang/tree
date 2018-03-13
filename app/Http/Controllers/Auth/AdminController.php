<?php

namespace App\Http\Controllers;

use App\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;


class AdminController extends Controller
{
    /**
     * 为指定用户显示详情
     *
     * @param int $id
     * @return Response
     */
    public function show($id)
    {
        $userid = DB::table('products')->insertGetId(
            ['name' => '柳树', 'pic' => 'public/demo.jpg']
        );
        // $userid = DB::table('feedback')->insertGetId(
        //     ['userid' => 0, 'name' => 'yyl','tel' => '15882233315','time' => date('Y-m-d H:i:s'),'content' => '好事！']
        // );
        //session(['userid'=>$userid]);
        //$value = $request->session()->all();
        // $res = session(['userid'=>5]);
        // var_dump($res);
        // $value = session('userid');
        var_dump($userid);
        //$users = DB::insert('insert into t_products (name,pic) values (?,?)',['柳树','public/demo.jpg']);
        return view('user',['userid'=>$id]);//->with('userid',$id);
    }
}