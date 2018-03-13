<?php

namespace App\Http\Controllers;

use App\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

\View::addExtension('html', 'php');

class UserController extends Controller
{
    // public function oAuth(){
    //     $wechat = config('wechat');
    //     var_dump($wechat);
    //     // $wechat->wechat->setMessageHandler(function($message){
    //     //     return "欢迎关注 苗圃！";
    //     // });

    //     return $wechat->server->serve();
        
    // }
    //验证消息
    // public function oAuth(Request $request)
    // {
    //     $data['signature'] = $request->get('signature');
    //     $data['timestamp'] = $request->get('timestamp');
    //     $data['nonce'] = $request->get('nonce');
    //     $data['echostr'] = $request->get('echostr');
    //     file_put_contents('./tokenCheck.txt', "---{<signature>:".$data['signature']."<timestamp>:".$data['timestamp']."<nonce>".$data['nonce']."<echoStr>".$data['echostr']."}---" , FILE_APPEND);
    //     // exit();
    //     if($this->checkSignature($data)){
    //         //$get["echoStr"] = $request->input('echoStr');
    //         echo $data["echostr"];
    //         exit;
    //     }
    // }
     
    // //检查签名
    // private function checkSignature($get)
    // {
    //     $signature = $get["signature"];
    //     $timestamp = $get["timestamp"];
    //     $nonce = $get["nonce"];
    //     $token = "tree";
    //     $tmpArr = array($token, $timestamp, $nonce);
    //     sort($tmpArr, SORT_STRING);
    //     $tmpStr = implode($tmpArr);
    //     $tmpStr = sha1($tmpStr);
    //     if($tmpStr == $signature){
    //         return true;
    //     }else{
    //         return false;
    //     }
    // }
    public function index($id){
        return view('index'); 
    }
    public function pageshow($id){
        return view('page'); 
    }
    public function feedin(){
        return view('feedback'); 
    }
    /**
     * 为指定用户显示详情
     *
     * @param int $id 产品id
     * @return Response
     */
    public function show($id)
    {
        $product = DB::table('products')->where('id',$id)->get();
        $returnData = array(
            'code' => 200,
            'msg' => '获取成功',
            'data' => array(
                'info' => (object)array(),
                'feedState' => 0
            )
        );
        if(!count($product)){
            $returnData['code'] = 404;
            $returnData['msg'] = '无效资源';
            return apiReturn($returnData);
            exit();
        }
        $returnData['data']['info'] = $product[0];
        $returnData['data']['links'] = DB::table('pages')->where('pid',0)->select('id','type','iconname','content','pic')->get();
        $userId = session('userId')?session('userId'):0;
        $repeat = 0;
        if($userId!=0){
            $repeat = DB::table('scans')->where(['userid'=>$userId,'pid'=>$id])->get();
            $repeat = count($repeat)?1:0;
        }
        $scanData = array(
            'userid' => $userId,//0为其他浏览器用户
            'pid' => $id,
            'time' => date("Y-m-d H:i:s"),
            'repeat' => $repeat,
        );
        DB::beginTransaction();
        $incResult = DB::table('products')->where('id',$id)->increment('scannum');
        $scanResult = DB::table('scans')->insertGetid($scanData);
        if(!$incResult||!$scanResult){
            DB::rollBack();
            $returnData['code'] = 500;
            $returnData['msg'] = '(不影响用户浏览)扫码统计失败,请重试...';
            return apiReturn($returnData);
            exit();
        }
        DB::commit();
        $banner = DB::table('banner')->select('pic')->orderBy('addtime','desc')->get();
        $returnData['data']['info']['banner'] = (object)array();
        if(count($banner)){
            $returnData['data']['info']['banner'] = $banner[0]['pic'];
        }
        $feedState = DB::table('set')->where('set','反馈开关')->get()->toArray();
        $returnData['data']['feedState'] = $feedState[0]['state'];
        return apiReturn($returnData);
    }
    public function childPage($id){
        $info = DB::table("pages")->where('id',$id)->get();
        $childPage = DB::table("pages")->where('pid',$id)->get();
        $returnData = array(
            'code' => 200,
            'msg' => '获取成功',
            'data' => array(
                'info' => (object)array(),
                'links' => $childPage,
                'feedState' => 0
            )
        );
        if(!count($info)){
            $returnData['code'] = 404;
            $returnData['msg'] = '无效资源';
            return apiReturn($returnData);
            exit();
        }
        $returnData['data']['info'] = $info[0];
        $banner = DB::table('banner')->select('pic')->get();
        $returnData['data']['info']['banner'] = (object)array();
        if(count($banner)){
            $returnData['data']['info']['banner'] = $banner[0]['pic'];
        }
        $feedState = DB::table('set')->where('set','反馈开关')->get()->toArray();
        $returnData['data']['feedState'] = $feedState[0]['state'];
        return apiReturn($returnData);
    }
    /*
    * 用户提交反馈
    */
   public function feedbackAdd(Request $request)
   {
        $post = $request->getcontent();
        $returnData = array(
            'code' => 200,
            'msg' => '反馈成功',
            'data' => array(),
        );
        $checkIsset = 'name,tel,content';
        $checkEmpty = 'name,tel,content';
        $apiData = apiCheckParameter($post,$checkIsset,$checkEmpty);
        if($apiData['code']!=200){
            $returnData['msg'] = $apiData['msg'];
            $returnData['code'] = $apiData['code'];
            return apiReturn($returnData);
            exit();
        }
        $reg = '/^1[3,4,5,7,8]\d{9}$/';
        if(!preg_match($reg,$apiData['apiData']['tel'])){
            $returnData['code'] = 400;
            $returnData['msg'] = '手机号正则验证不通过';
            return apiReturn($returnData);
            exit();
        }
        $data = array(
            'userid' => session('userId'),//用户id,若为其他浏览器扫码则userid为0
            'name' => $apiData['apiData']['name'],
            'tel' => $apiData['apiData']['tel'],
            'time' => date('Y-m-d H:i:s'),
            'content' => $apiData['apiData']['content']
        );
        $feedbackId = DB::table('feedback')->insertGetid($data);
        if(!$feedbackId){
            $returnData['msg'] = '反馈失败,请重试...';
        }
        return apiReturn($returnData);
    }
}