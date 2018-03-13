<?php
    /*
    *方法：api访问检查参数
    *参数： 
    *$apiData:api得到的数据
    *$checkIsset:要检查的必填参数
    *$checkEmpty:要检查的不能为空的参数
    */
    function apiCheckParameter($apiData,$checkIsset,$checkEmpty){
        $apiData = json_decode($apiData,true);
        $checkIsset = explode(',',$checkIsset);
        for($i=0;$i<count($checkIsset);$i++){
            if(!isset($apiData[$checkIsset[$i]])){
                $data['code']=400;
                $data['msg']='请求参数错误';
                return $data;
            }
        }
        $checkEmpty = explode(',',$checkEmpty);
        for($i=0;$i<count($checkEmpty);$i++){
            if($apiData[$checkEmpty[$i]]===''){
                $data['code']=400;
                $data['msg']='参数'.$checkEmpty[$i].'不能为空';
                return $data;
            }
        }
        $data['code'] = 200;
        $data['apiData'] = $apiData;
        return $data;
    }
    /*
    *方法：api返回数据
    *参数：
    *$returnData:api返回数据
    *$contentType：header头信息
    */
    function apiReturn($returnData,$contentType='text/plain'){
        return response(json_encode($returnData), $returnData['code'])->header('Content-Type', $contentType);
    }
    /*
    *方法：压缩并保存图片
    *参数 
    *$folder：保存目录
    *$base64_image_content：base64格式图片
    *$quality：图片保存的是质量（范围1-100）
    *$n_w：压缩宽度（单位：px）
    *$n_h：压缩高度（单位：px）
    */
    function saveBase64Image($folder,$base64_image_content,$quality=50,$n_w=64,$n_h=64){
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/',$base64_image_content,$result)){
            //图片后缀
            $type = $result[2];
            //保存位置--图片名
            $image_name=date('His').str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT).".".$type;
            $image_url = 'img/'.$folder.'/'.date('Ymd').'/';      
            if(!file_exists($image_url)) {
                mkdir($image_url,0777,true);
            }                
            //解码
            $image_url .= $image_name;
            $decode=base64_decode(str_replace($result[1], '', $base64_image_content));
            if (file_put_contents($image_url, $decode)){
                list($width, $height)=getimagesize($image_url);
                $new=imagecreatetruecolor($n_w, $n_h);                
                $color=imagecolorallocatealpha($new,255,255,255,127); 
                imagesavealpha($new,true);
                imagecolortransparent($new,$color); 
                imagefill($new,0,0,$color); 
                switch($type){
                    case 'png':
                        $img = imagecreatefrompng($image_url);
                        break;
                    case 'gif':
                        $img = imagecreatefromgif($image_url);
                        break;
                    default:
                        $img = imagecreatefromjpeg($image_url);
                        break;
                }
                //copy部分图像并调整
                imagecopyresized($new,$img,0,0,0,0,$n_w,$n_h,$width,$height);
                //图像输出新图片、另存为
                $result = imagejpeg($new,$image_url,$quality);
                imagedestroy($new);
                imagedestroy($img);
                if($result){
                    $data['code']=200;
                    $data['imageName']=$image_name;
                    $data['url']=$image_url;
                    $data['msg']='保存成功！';
                }else{
                    $data['code']=500;
                    $data['msg']='图片压缩失败！';
                }
            }else{
                $data['code']=500;
                $data['msg']='图片保存失败！';
            }
        }else{
            $data['code']=400;
            $data['msg']='base64图片格式有误！';
        }       
        return $data;
    }
    //curl get
    function httpGet($url) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 500);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_URL, $url);

        $res = curl_exec($curl);
        curl_close($curl);

        return $res;
    }
    //curl post
    function httpPost($url,$param){
        $oCurl = curl_init();
        if(stripos($url,"https://")!==FALSE){
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($oCurl, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
        }
        if (is_string($param)) {
            $strPOST = $param;
        } else {
            $aPOST = array();
            foreach($param as $key=>$val){
                $aPOST[] = $key."=".urlencode($val);
            }
            $strPOST =  join("&", $aPOST);
        }
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt($oCurl, CURLOPT_POST,true);
        curl_setopt($oCurl, CURLOPT_POSTFIELDS,$strPOST);
        $sContent = curl_exec($oCurl);
        $aStatus = curl_getinfo($oCurl);
        curl_close($oCurl);
        if(intval($aStatus["http_code"])==200){
            return $sContent;
        }else{
            return false;
        }
    }
    function getToken($appid, $refresh = false){
        $data = M('wx_config')
                ->where(array('appid'=>$appid))
                ->field('appid,appsecret,access_token,token_expire_time')
                ->find();
        if ( empty($data['access_token']) || $data['token_expire_time'] < time() || $refresh) {
           //token过期或者强制刷新
          // 如果是企业号用以下URL获取access_token
          // $url = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=$this->appId&corpsecret=$this->appSecret";
          //解密
          $data['appsecret'] = authcode($data['appsecret'],'DECODE',$data['appid']);
          $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$data['appid'].'&secret='.$data['appsecret'];
          $res = json_decode(httpGet($url), true);
          $access_token = $res['access_token'];
          if ($access_token) {
            M('wx_config')
            ->where(array('appid'=>$appid))
            ->data(array('access_token'=>$access_token, 'token_expire_time'=>time()+7000))
            ->save();
          }
        }else{
            $access_token = $data['access_token'];
        }
        return $access_token; 
    }
    function addFileToZip($path,$zip){
        $handler=opendir($path); //打开当前文件夹由$path指定。
        while(($filename=readdir($handler))!==false){
            if($filename != "." && $filename != ".."){//文件夹文件名字为'.'和‘..'，不要对他们进行操作
                if(is_dir($path."/".$filename)){// 如果读取的某个对象是文件夹，则递归
                    addFileToZip($path."/".$filename, $zip);
                }else{ //将文件加入zip对象
                    $zip->addFile($path."/".$filename);
                }
            }
        }
        @closedir($path);
    }         