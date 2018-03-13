<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
/*
	* User
    * 用户页
*/
 Route::group(['middleware' => ['wechat.oauth']], function () {
	//微信鉴权
	//Route::any('/userAuth', 'UserController@oAuth');
	//用户扫码首页
	Route::get('/index/{id}', 'UserController@index');
	//用户扫码首页
	Route::get('/index/id/{id}', 'UserController@show');
	//其他页面
	Route::get('/page/{id}', 'UserController@pageshow');
	//其他页面
	Route::get('/childPage/{id}', 'UserController@childPage');
	//添加反馈
	Route::get('/feedin', 'UserController@feedin');
	//添加反馈
	Route::post('/feedbackAdd', 'UserController@feedbackAdd');
 });
/*
|---------------------------------------------------------------------------
*/
/*
	* Admin
    * 管理页
*/
//后台登陆
// Route::post('/admin', 'AdminController@adminIndex');	
Route::group(['middleware' => ['adminAuth']], function () {
    //保存编辑器图片
	Route::post('/editorImg', 'AdminController@getEditorImg');
    //获取反馈状态
	Route::get('/getFeedbackState', 'AdminController@getFeedbackState');
	//反馈开关
	Route::get('/feedbackState/state/{state}', 'AdminController@feedbackState');
	//反馈列表
	Route::get('/feedbackList', 'AdminController@feedbackList');///offset/{offset}/limit/{limit}/order/{order}
	//反馈删除
	Route::get('/feedbackDelete/id/{id}', 'AdminController@feedbackDelete');
	//反馈列表导出
	Route::get('/feedbackList/excelExport', 'AdminController@excelExport');
	//批量导入、添加产品信息
	Route::post('/productAdd/excelImport', 'AdminController@excelImport');
	//添加产品信息
	Route::post('/productAdd', 'AdminController@productAdd');
	//修改产品信息
	Route::post('/productChange', 'AdminController@productChange');
	//删除产品
	Route::get('/productDelete/id/{id}', 'AdminController@productDelete');
	//产品列表
	Route::get('/productList/type/{type}', 'AdminController@productList');
	//产品搜索
	Route::get('/productSearch/keyword/{keyword}/special/{special}', 'AdminController@productSearch');
	//产品详情
	Route::get('/productInfo/id/{id}', 'AdminController@productInfo');
	//banner图
	Route::post('/banner', 'AdminController@banner');
	//页面列表
	Route::get('/pageList', 'AdminController@pageList');
	//页面详情
	Route::get('/pageInfo/id/{id}', 'AdminController@PageInfo');
	//页面层级列表
	Route::get('/pageHasPid', 'AdminController@pageHasPid');
	//页面添加
	Route::post('/pageAdd', 'AdminController@pageAdd');
	//页面更新
	Route::post('/pageUpdate', 'AdminController@pageUpdate');
	//页面删除
	Route::get('/pageDelete/id/{id}', 'AdminController@pageDelete');
	//扫码统计
	Route::get('/scanCount', 'AdminController@scanCount');
	//获取banner图
	Route::get('/getBanner', 'AdminController@getBanner');
	//后台退出
	Route::get('/loginOut', 'AdminController@loginOut');
	//批量导出二维码
	Route::get('/exportQrcode/{type}', 'AdminController@exportQrcode');
	//下载二维码
	Route::get('/downQrcode/{id}', 'AdminController@downQrcode');
});
//后台登陆
Route::post('/login', 'AdminController@login');


// Route::get('/{vue_capture?}', function () {
//    return view('admin.home');
//  })->where('vue_capture', '[\/\w\.-]*');