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

// +----------------------------------------------------------------------
// | 缓存设置
// +----------------------------------------------------------------------

return [
    // 缓存配置为复合类型
    'type'  =>  'complex', 
    'default'	=>	[
      	'type'	=>	'file',
      	// 全局缓存有效期（0为永久有效）
      	'expire'=>  0, 
      	// 缓存前缀
      	'prefix'=>  'think',
       	// 缓存目录
      	'path'  =>  '../runtime/cache/',
    ],
    'redis'	=>	[
      	'type'	=>	'redis',
      	'host'	=>	'127.0.0.1',
      	// 全局缓存有效期（0为永久有效）
      	'expire'=>  0, 
      	// 缓存前缀
      	'prefix'=>  'think',
    ],    
    // 添加更多的缓存类型设置
];
