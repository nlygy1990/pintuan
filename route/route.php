<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

Route::get('think', function () {
    return 'hello,ThinkPHP5!';
});
//几个固定的路由
Route::rule('verify','admin/base/verify'); //验证码
Route::rule('admin/login','admin/login/index'); // 登录
Route::rule('admin/checkpwd','admin/login/checkpwd'); // 登录密码校验
Route::rule('admin/logout','admin/login/logout'); // 登录密码校验
Route::rule('admin/index','admin/index/index'); //首页
Route::rule('admin/main','admin/index/main'); //首页

return [

];
