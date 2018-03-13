<?php

namespace App\Http\Controllers;

use App\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Excel;
use QrCode;
use Zipper;

class AdminController extends Controller
{
    // public function adminIndex(){
    //     return view('admin/index');
    // }

    public function login(Request $request){
        $post = $request->getcontent();
        $returnData = array(
            'code' => 200,
            'msg' => '登录成功',
            'data' => (object)array(), 
        );
        
        $checkIsset = 'name,pwd';
        $checkEmpty = 'name,pwd';
        $apiData = apiCheckParameter($post,$checkIsset,$checkEmpty);
        if($apiData['code']!=200){
            $returnData['msg'] = $apiData['msg'];
            $returnData['code'] = $apiData['code'];
            return apiReturn($returnData);
            exit();
        }
        $count = DB::table('admin')->where('root',$apiData['apiData']['name'])->where('pwd',md5($apiData['apiData']['pwd']))->count();
        if(!$count){
            $returnData['code'] = 401;
            $returnData['msg'] = '用户名或密码错误';
            return apiReturn($returnData);
            exit();
        }
        $sessionData = array(
            'role' => 'admin',
            'name' => $apiData['apiData']['name'],
            'loginTime' => date("Y-m-d H:i:s")
        );
        session($sessionData);
        $returnData['session'] = $request->session()->all();
        return apiReturn($returnData);
    }
    public function loginOut(Request $request){
        $returnData = array(
            'code' => 200,
            'msg' => '退出成功',
            'data' => (object)array(), 
        );
        $request->session()->flush();
        return apiReturn($returnData);
    }
    /**
     * 反馈开关
     * @param int $state 更改的状态
     * @return Response
    **/
    public function feedbackState($state)
    {
        $updateResult = DB::table('set')->where('set','反馈开关')->update(['state'=>$state]);
        $returnData = array(
            'code' => 200,
            'msg' => '修改成功',
            'data' => (object)array()
        );
        if($updateResult===false){
            $returnData['code'] = 500;
            $returnData['msg'] = '修改失败，请重试';//'起始索引：'.$offset.',记录数：'.$limit.',按'.$order.排序
        }
        return apiReturn($returnData);
    }
    /**
     * 反馈列表
     * @param int $offset 起始索引
     * @param int $limit 记录数
     * @param string $order 排序('asc','desc')
     * @return Response
    **/
    public function feedbackList()//$offset=0,$limit=5,$order="desc" ->offset($offset)->limit($limit)->
    {
        $list = DB::table('feedback')->orderBy('time','desc')->get();
        $state = DB::table('set')->where('set','反馈开关')->get();
        $returnData = array(
            'code' => 200,
            'msg' => '获取成功',
            'data' => array(
                'list' => $list,
                'set' => count($state)?$state[0]:(object)array()
            )
        );
        if(!count($list)){
            $returnData['msg'] = '无记录';//'起始索引：'.$offset.',记录数：'.$limit.',按'.$order.排序
        }
        return apiReturn($returnData);
    }
    /**
     * 反馈删除
     * @return Response
    **/
    public function feedbackDelete($id)//$offset=0,$limit=5,$order="desc" ->offset($offset)->limit($limit)->
    {
        $deleteResult = DB::table('feedback')->where('id',$id)->delete();
        $returnData = array(
            'code' => 200,
            'msg' => '删除成功',
            'data' => (object)array()
        );
        if(!$deleteResult){
            $returnData['code'] = 500;
            $returnData['msg'] = '删除失败，请重试';//'起始索引：'.$offset.',记录数：'.$limit.',按'.$order.排序
        }
        return apiReturn($returnData);
    }
    /**
     * 反馈导出
    **/
    public function excelExport(){
        $cellData = DB::table('feedback')->select('id','name','tel','content','time')->orderBy('time','desc')->get()->toArray();
        foreach($cellData as $key => $value){
            $cellData[$key]['id'] = $key;
        }
        //$cellData自己要进行导出的数组
        array_unshift($cellData,array('id'=>'编号','name'=>'姓名','tel'=>'手机号','time'=>'时间','content'=>'反馈意见')); 
        Excel::create('反馈列表',function($excel) use ($cellData){  
            $excel->sheet('feedbackList', function($sheet) use ($cellData){  
                $sheet->rows($cellData);  
            });  
        })->export('xls'); 
    } 
    /**
     * 产品批量导入、添加
    **/
    public function excelImport(Request $request){
        $post = $request->all();
        $pathName = $post['file']->getPathName();
        $this->type = (int)$post['type'];
        $this->pic = $post['pic'];
        Excel::load($pathName, function($reader) {
            $reader = $reader->getSheet(0);
            //获取表中的数据
            $results = $reader->toArray();
            $now = date("Y-m-d H:i:s");
            $returnData = array(
                'code' => 200,
                'msg' => ($this->type==1)?'"一树一码"批量导入成功':'"一批一码"批量导入成功',
                'data' => array()
            );
            if(count($results)<2){
                $returnData['code'] = 400;
                $returnData['msg'] = '请勿上传空表格';
            }else{
                $flag = true;
                $imgData = saveBase64Image('product',$this->pic,60,280,280);
                if($imgData['code']!=200){
                    $returnData['msg'] = $imgData['msg'];
                    $returnData['code'] = $imgData['code'];
                }else{
                    foreach ($results as $key => $value) {
                        if($key!=0){
                            if((!isset($value[8])&&$this->type==0)||(isset($value[8])&&$this->type==1)){
                                $returnData['code'] = 400;
                                $returnData['msg'] = '表格格式错误，请参照'.(($this->type==1)?'"一树一码"':'"一批一码"').'模板编辑';
                                $flag = false;
                                break;
                            }
                            $data[$key-1]['name'] = $value[0];
                            $data[$key-1]['spec'] = $value[6];
                            $data[$key-1]['special'] = $this->type;
                            $data[$key-1]['num'] = ($this->type==1)?1:$value[7];
                            $data[$key-1]['content'] = '<p>中文名字：'.$value[0].'</p>';
                            $data[$key-1]['content'] .= '<p>拉丁名：'.$value[1].'</p>';
                            $data[$key-1]['content'] .= '<p>别称：'.$value[2].'</p>';
                            $data[$key-1]['content'] .= '<p>科：'.$value[3].'</p>';
                            $data[$key-1]['content'] .= '<p>属：'.$value[4].'</p>';
                            $data[$key-1]['content'] .= '<p>种：'.$value[5].'</p>';
                            $data[$key-1]['content'] .= '<p>规格：'.$value[6].'</p>';
                            $data[$key-1]['content'] .= '<p>备注：'.(($this->type==1)?$value[7]:$value[8]).'</p>';
                            $data[$key-1]['pic'] = $imgData['url'];
                            $data[$key-1]['addtime'] = $now;
                        }
                    }
                    if($flag){
                        $insertResult = DB::table("products")->insert($data);
                        if(!$insertResult){
                            $returnData['msg'] = (($this->type==1)?'"一树一码"':'"一批一码"').'批量导入失败，请重试...';
                        } 
                    } 
                }  
            }                    
            $this->returnData = $returnData;
        });
        return apiReturn($this->returnData);    
    } 
    /**
     * 产品添加
     * @return Response
    **/
    public function productAdd(Request $request){
        $post = $request->getcontent();
        $returnData = array(
            'code' => 200,
            'msg' => '添加成功',
            'data' => (object)array()
        );
        $checkIsset = 'special,name,pic,spec,num,price,content';
        $checkEmpty = 'special,name,pic,content';
        $apiData = apiCheckParameter($post,$checkIsset,$checkEmpty);
        if($apiData['code']!=200){
            $returnData['msg'] = $apiData['msg'];
            $returnData['code'] = $apiData['code'];
            return apiReturn($returnData);
            exit();
        }
        $imgData = saveBase64Image('product',$apiData['apiData']['pic'],60,280,280);
        if($imgData['code']!=200){
            $returnData['msg'] = $imgData['msg'];
            $returnData['code'] = $imgData['code'];
            return apiReturn($returnData);
            exit();
        }
        $data = array(
            'special' => (int)$apiData['apiData']['special']?1:0,
            'name' => $apiData['apiData']['name'],
            'pic' => $imgData['url'],
            'spec' => $apiData['apiData']['spec'],
            'num' => (int)$apiData['apiData']['special']?1:$apiData['apiData']['num'],
            'price' => $apiData['apiData']['price'],
            'content' => $apiData['apiData']['content'],
            'addtime' => date("Y-m-d H:i:s")
        );
        $productId = DB::table('products')->insertGetid($data);
        if(!$productId){
            $returnData['code'] = 500;
            $returnData['msg'] = '添加失败,请重试...';
        }
        return apiReturn($returnData);
    }
    /**
     * 产品列表
     * @return Response
    **/
    public function productList($type){
        $products = DB::table('products')->where('special',$type)->orderBy('addtime','desc')->get();
        $returnData = array(
            'code' => 200,
            'msg' => '获取成功',
            'data' => array(
                'products' => $products
            )
        );
        if(!count($products)){
            $returnData['msg'] = '暂无数据';
        }
        return apiReturn($returnData);
    }
    /**
     * 产品搜索
     * $keyword 关键字
     * $special 产品类型（0：一批一码，1：一树一码）
     * @return Response
    **/
    public function productSearch($keyword='',$special=0){
        $products = DB::table('products')
            ->where('special',(int)$special)
            ->where('name', 'like', '%'.$keyword.'%')
            ->get();
        $returnData = array(
            'code' => 200,
            'msg' => '获取成功',
            'data' => array(
                'products' => $products
            )
        );
        if(!count($products)){
            $returnData['msg'] = '暂无数据';
        }
        return apiReturn($returnData);
    }
    /**
     * 产品详情
     * @return Response
    **/
    public function productInfo($id){
        $info = DB::table('products')->where('id',$id)->get();
        $returnData = array(
            'code' => 200,
            'msg' => '获取成功',
            'data' => array(
                'info' => $info[0]
            )
        );
        if(!count($info)){
            $returnData['code'] = 404;
            $returnData['msg'] = '无效资源';
        }
        return apiReturn($returnData);
    }
    public function getEditorImg(Request $request){
        $post = $request->all();
        $clientName = $post['file']->getClientOriginalName();//文件原名
        //$pathName = $post['file']->getPathName();
        // $file = $request->file('image');    
        //$filetype = $post['file']->getMimeType();               //文件类型
        //$last = $post['file']->getClientOriginalExtension();//图片后缀
        //$save =  $clientName.'.'.$last;
        $result = $post['file']->move('img/editorImg/'.date('Ymd').'/',$clientName);
        $returnData = array(
            'code' => 200,
            'msg' => '图片保存成功',
            'data' => array(
                'src' => ''
            )
        );
        if(!$result){
            $returnData['code'] = 500;
            $returnData['msg'] = '图片保存失败';
            return apiReturn($returnData);
            exit();
        }
        $returnData['data']['src'] = 'img/editorImg/'.date('Ymd').'/'.$clientName;
        return apiReturn($returnData);
    }
    /**
     * 产品删除
     * @return Response
    **/
    public function productDelete($id){       
        $oldPic = DB::table('products')->select('pic')->where('id',$id)->get();
        $deleteResult = DB::table('products')->where('id',$id)->delete();
        $returnData = array(
            'code' => 200,
            'msg' => '删除成功',
            'data' => (object)array()
        );
        if($deleteResult===false){
            $returnData['code'] = 404;
            $returnData['msg'] = '无效资源';
            return apiReturn($returnData);
            exit();
        }
        $other = DB::table('products')->where('pic',$oldPic[0]['pic'])->count();
        if(!$other){
            @unlink($oldPic[0]['pic']);
        }
        return apiReturn($returnData);
    }
    /**
     * 产品修改
     * @return Response
    **/
    public function productChange(Request $request){
        $post = $request->getcontent();
        $returnData = array(
            'code' => 200,
            'msg' => '更新成功',
            'data' => (object)array()
        );
        $checkIsset = 'pid,special,name,pic,spec,num,price,content';
        $checkEmpty = 'pid,special,name,content';
        $apiData = apiCheckParameter($post,$checkIsset,$checkEmpty);
        if($apiData['code']!=200){
            $returnData['msg'] = $apiData['msg'];
            $returnData['code'] = $apiData['code'];
            return apiReturn($returnData);
            exit();
        }
        $data = array(
            'special' => (int)$apiData['apiData']['special']?1:0,
            'name' => $apiData['apiData']['name'],
            'spec' => $apiData['apiData']['spec'],
            'num' => (int)$apiData['apiData']['special']?1:$apiData['apiData']['num'],
            'price' => $apiData['apiData']['price'],
            'content' => $apiData['apiData']['content'],
            //'addtime' => date("Y-m-d H:i:s")
        );
        if(!empty($apiData['apiData']['pic'])){
            $imgData = saveBase64Image('product',$apiData['apiData']['pic'],60,280,280);
            if($imgData['code']!=200){
                $returnData['msg'] = $imgData['msg'];
                $returnData['code'] = $imgData['code'];
                return apiReturn($returnData);
                exit();
            }
            $data['pic'] = $imgData['url'];
            $oldPic = DB::table('products')->select('pic')->where('id',$apiData['apiData']['pid'])->get();
            $other = DB::table('products')->where('pic',$oldPic[0]['pic'])->count();
            if(!$other){
                @unlink($oldPic[0]['pic']);
            }
        }
        $updateResult = DB::table('products')->where('id',$apiData['apiData']['pid'])->update($data);
        if($updateResult===false){
            $returnData['msg'] = '更新失败,请重试...';
        }
        return apiReturn($returnData);
    }
    /**
     * banner图
     * @return Response
    **/
    public function banner(Request $request){
        $post = $request->getcontent();
        $returnData = array(
            'code' => 200,
            'msg' => '操作成功',
            'data' => (object)array()
        );
        $checkIsset = 'bid,action,pic';
        $checkEmpty = 'bid,action,pic';
        $apiData = apiCheckParameter($post,$checkIsset,$checkEmpty);
        if($apiData['code']!=200){
            $returnData['msg'] = $apiData['msg'];
            $returnData['code'] = $apiData['code'];
            return apiReturn($returnData);
            exit();
        }
        $imgData = saveBase64Image('banner',$apiData['apiData']['pic'],60,640,300);
        if($imgData['code']!=200){
            $returnData['msg'] = $imgData['msg'];
            $returnData['code'] = $imgData['code'];
            return apiReturn($returnData);
            exit();
        }        
        $data = array(
            'pic' => $imgData['url'],
            'addtime' => date("Y-m-d H:i:s")
        );
        switch ($apiData['apiData']['action']) {
            case 'add':
                $sort = DB::table('banner')->select('sort')->orderBy('id','desc')->limit(1)->get();
                $sort = count($sort)?$sort[0]['sort']:0;
                $data['sort'] = (int)$sort+1;
                $bannerId = DB::table('banner')->insertGetid($data);
                $returnData['data']['bannerId'] = $bannerId;
                break;
            case 'change':
                $oldPic = DB::table('banner')->select('pic')->where('id',$apiData['apiData']['bid'])->get();
                @unlink($oldPic[0]['pic']);
                $bannerId = DB::table('banner')->where('id',$apiData['apiData']['bid'])->update($data);
                break;
            case 'delete':
                $oldPic = DB::table('banner')->select('pic')->where('id',$apiData['apiData']['bid'])->get();
                @unlink($oldPic[0]['pic']);
                $bannerId = DB::table('banner')->where('id',$apiData['apiData']['bid'])->delete();
                break;
            default:
                $bannerId = 0;
                $returnData['msg'] = '无效操作';
                $returnData['code'] = 404;
                break;
        }
        if(!$bannerId){
            $returnData['msg'] = $apiData['apiData']['action'].'失败,请重试...';
        }
        return apiReturn($returnData);
    }
     /**
     * 获取banner图
     * @return Response
    **/
    public function getBanner(){
        $banner = DB::table('banner')->get();
        $returnData = array(
            'code' => 200,
            'msg' => '获取成功',
            'data' => array(
                'banner' => count($banner)?$banner[count($banner)-1]:(object)array()
            )
        );
        if(!count($banner)){
            $returnData['msg'] = '无记录';
        }
        return apiReturn($returnData);
    }
    /**
     * 页面列表
     * @return Response
    **/
    public function pageList(){
        $pages = DB::table('pages')->where('pid',0)->orderBy('addtime','desc')->get()->toArray();
        for($i=0;$i<count($pages);$i++){
            $pages[$i]['childPage'] = DB::table('pages')->where('pid',$pages[$i]['id'])->orderBy('addtime','desc')->get();
        }
        $returnData = array(
            'code' => 200,
            'msg' => '获取成功',
            'data' => array(
                'pages' => $pages
            )
        );
        if(!count($pages)){
            $returnData['msg'] = '无记录';
        }
        return apiReturn($returnData);
    }
    /**
     * 页面详情
     * @return Response
    **/
    public function pageInfo($id){
        $info = DB::table('pages')->where('id',$id)->get();
        $returnData = array(
            'code' => 200,
            'msg' => '获取成功',
            'data' => array(
                'info' => count($info)?$info[0]:(object)array()
            )
        );
        if(!count($info)){
            $returnData['code'] = 404;
            $returnData['msg'] = '无效资源';
        }
        return apiReturn($returnData);
    }
    /**
     * 页面层级
     * @return Response
    **/
    public function pageHasPid(){
        $pids = DB::table('pages')->where('pid',0)->get();
        $returnData = array(
            'code' => 200,
            'msg' => '获取成功',
            'data' => array(
                'pages' => $pids
            )
        );
        if(!count($pids)){
            $returnData['msg'] = '无记录';
        }
        return apiReturn($returnData);
    }
    /**
     * 页面添加
     * @return Response
    **/
    public function pageAdd(Request $request){
        $post = $request->getcontent();
        $returnData = array(
            'code' => 200,
            'msg' => '添加成功',
            'data' => (object)array()
        );
        $checkIsset = 'pid,showbanner,iconname,type,content,pic';
        $checkEmpty = 'pid,showbanner,iconname,type,content,pic';
        $apiData = apiCheckParameter($post,$checkIsset,$checkEmpty);
        if($apiData['code']!=200){
            $returnData['msg'] = $apiData['msg'];
            $returnData['code'] = $apiData['code'];
            return apiReturn($returnData);
            exit();
        }
        $imgData = saveBase64Image('icon',$apiData['apiData']['pic'],60,128,128);
        if($imgData['code']!=200){
            $returnData['msg'] = $imgData['msg'];
            $returnData['code'] = $imgData['code'];
            return apiReturn($returnData);
            exit();
        }        
        $data = array(
            'pid' => $apiData['apiData']['pid'],
            'showbanner' => (int)$apiData['apiData']['showbanner']?1:0,
            'pic' => $imgData['url'],
            'iconname' => $apiData['apiData']['iconname'],
            'type' => (int)$apiData['apiData']['type']?1:0,
            'content' => $apiData['apiData']['content'],
            'addtime' => date("Y-m-d H:i:s")
        );
        $pageId = DB::table('pages')->insertGetid($data);
        if(!$pageId){
            $returnData['code'] = 500;
            $returnData['msg'] = '添加失败,请重试...';
        }
        return apiReturn($returnData);
    }
    /**
     * 页面更新
     * @return Response
    **/
    public function pageUpdate(Request $request){
        $post = $request->getcontent();
        $returnData = array(
            'code' => 200,
            'msg' => '更新成功',
            'data' => (object)array()
        );
        $checkIsset = 'id,pid,showbanner,iconname,type,content,pic';
        $checkEmpty = 'id,pid,showbanner,iconname,type,content';
        $apiData = apiCheckParameter($post,$checkIsset,$checkEmpty);
        if($apiData['code']!=200){
            $returnData['msg'] = $apiData['msg'];
            $returnData['code'] = $apiData['code'];
            return apiReturn($returnData);
            exit();
        }
        $data = array(
            'pid' => $apiData['apiData']['pid'],
            'showbanner' => (int)$apiData['apiData']['showbanner']?1:0,
            'iconname' => $apiData['apiData']['iconname'],
            'type' => (int)$apiData['apiData']['type']?1:0,
            'content' => $apiData['apiData']['content'],
            'addtime' => date("Y-m-d H:i:s")
        );
        if(!empty($apiData['apiData']['pic'])){
            $imgData = saveBase64Image('icon',$apiData['apiData']['pic'],60,128,128);
            if($imgData['code']!=200){
                $returnData['msg'] = $imgData['msg'];
                $returnData['code'] = $imgData['code'];
                return apiReturn($returnData);
                exit();
            } 
            $data['pic'] = $imgData['url'];  
            $oldPic = DB::table('pages')->select('pic')->where('id',$apiData['apiData']['id'])->get();
            @unlink($oldPic[0]['pic']);
        }
        $updateResult = DB::table('pages')->where('id',$apiData['apiData']['id'])->update($data);
        if($updateResult===false){ 
            $returnData['code'] = 500;
            $returnData['msg'] = '更新失败,请重试...';
        }
        return apiReturn($returnData);
    }
    /**
     * 页面删除
     * @return Response
    **/
    public function pageDelete($id){
        $returnData = array(
            'code' => 200,
            'msg' => '删除成功',
            'data' => (object)array()
        );
        $delete = DB::table('pages')->where('id',$id)->get();
        $deletes = array();
        if($delete[0]['pid']!=0){
           $deletes = DB::table('pages')->where('pid',$delete[0]['pid'])->get();
        }   
        DB::beginTransaction();  
        $resultDelete = DB::table('pages')->where('id',$delete[0]['id'])->delete();
        if(!$resultDelete){
            DB::rollBack();
            $returnData['code'] = 500;
            $returnData['msg'] = '删除失败,请重试...';
            return apiReturn($returnData);
            exit();
        }
        @unlink($delete[0]['pic']);  
        for($i=0;$i<count($deletes);$i++){
            $result[$i] = DB::table('pages')->where('id',$deletes[$i]['id'])->update(['pid'=>0]);
            if($result[$i]===false){
                DB::rollBack();
                $returnData['code'] = 500;
                $returnData['msg'] = '删除失败,请重试...';
                return apiReturn($returnData);
                exit();
            }
        }
        DB::commit();
        return apiReturn($returnData);
    }
    public function scanCount(){
        $returnData = array(
            'code' => 200,
            'msg' => '获取成功',
            'data' => array(
                'scanTotal' => 0,
                'otherScan' => 0,
                'user' => 0,
                'scanPer' => 0.00,
                'scanRepeatPer' => 0.00
            )
        );
        //总用户数
        $user = DB::table('user')->where('openid','!=','')->count();
        if(!$user){
            $returnData['msg'] ='暂无用户';
            return apiReturn($returnData);
            exit();
        }
        //总扫码量
        $scanTotal = DB::table('scans')->count();
        //重复扫码量
        $scanRepeat = DB::table('scans')->where('repeat',1)->count();
        //人均扫码次数
        $scanPer = number_format($scanTotal/$user, 2, '.', '');
        //重复扫码率
        $scanRepeatPer = number_format($scanRepeat/$user, 2, '.', '');
        $returnData['data'] = array(
            'scanTotal' => $scanTotal,
            'user' => $user,
            'scanPer' => $scanPer,
            'scanRepeatPer' => $scanRepeatPer,
        );
        return apiReturn($returnData);
    }
    public function exportQrcode($type){
        //$qrcode = new BaconQrCodeGenerator;  
        //$res = $qrcode->size(500)->generate('Welcome to LaravelAcademy!');          
        $products = DB::table('products')->where('special',(int)$type)->orderBy('addtime','desc')->get();
        $file = 'img/qrcode/'.date('Ymd').'/';
        if(!file_exists($file)) {
            mkdir($file,0777,true);
        } 
        $url = 'http://tree.91zsc.com/index/'; 
        for($i=0;$i<count($products);$i++){
            $name[$i] = "编号".$products[$i]['id'].$products[$i]['name'].".png";
            QrCode::format('png')->size(800)->encoding('UTF-8')->generate($url.$products[$i]['id'],public_path($file.$name[$i]));
        }
        $files = glob($file."*");
        $zipname = 'img/qrcode/'.date('Ymd').'.zip';
        Zipper::make($zipname)->add($files)->close();
        $filesize = filesize($zipname);
        header ( "Cache-Control: max-age=0" );
        header ( "Content-Description: File Transfer" );
        header ( 'Content-disposition: attachment; filename=' . basename( $zipname ) ); // 文件名
        header("Content-Type:application/download");
        header("Content-Type:application/force-download");
        header ( "Content-Type: application/zip" ); // zip格式的
        header ( "Content-Transfer-Encoding: binary" ); // 告诉浏览器，这是二进制文件
        header ( 'Content-Length: ' . $filesize ); // 告诉浏览器，文件大小
        header("Content-Range: 0-".($filesize-1)."/".$filesize);// 下载进度条
        readfile ( $zipname );// 输出创建文件;
        @unlink ( $zipname );// 删除创建的文件;
    }
    public function downQrcode($id){         
        // $returnData = array(
        //     'code' => 200,
        //     'msg' => '获取成功',
        //     'data' => (object)array()
        // );
        $products = DB::table('products')->where('id',(int)$id)->get();
        if(!count($products)){
            $returnData['code'] = 404;
            $returnData['msg'] = '无效资源';
            return apiReturn($returnData);
        }
        $file = 'img/sigleQrcode/'.date('Ymd').'/';
        if(!file_exists($file)) {
            mkdir($file,0777,true);
        } 
        $url = 'http://tree.91zsc.com/index/'; 
        $name = "编号".$products[0]['id'].$products[0]['name'].".png";
        QrCode::format('png')->size(800)->encoding('UTF-8')->generate($url.$products[0]['id'],public_path($file.$name));
        // $returnData['data'] = $file.$name;
        // return apiReturn($returnData);
        $file1 = $file.$name; // 要下载的文件
        $content = file_get_contents($file1);
        header('Pragma: public');
        header('Last-Modified:'.gmdate('D, d M Y H:i:s') . 'GMT');
        header('Cache-Control:no-store, no-cache, must-revalidate');
        header('Cache-Control:pre-check=0, post-check=0, max-age=0');
        header('Content-Transfer-Encoding:binary'); 
        header('Content-Encoding:none');
        header('Content-type:multipart/form-data');
        header('Content-Disposition:attachment; filename='.$name); //设置下载的默认文件名
        header('Content-length:'. strlen($content));
        echo $content;
    }
}