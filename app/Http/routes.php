<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/


//项目导出
Route::get('/export/{id}',[
    'as' => 'document.export', 'uses' => 'DocumentController@export'
]);


/**
 * 网站设置
 */
Route::group(['middleware' => 'super.member','prefix' => 'setting'],function (){
    //站点设置
    Route::match(['get','post'],'site',[
        'uses' => 'SettingController@site'
    ])->name('setting.site');
});


Route::group(['middleware' => 'authorize','prefix' => 'project'],function (){
    //创建项目
    Route::match(['get','post'],'create',[
        'uses' => 'ProjectController@create'
    ])->name('project.create');

    //编辑项目
    Route::match(['get','post'],'edit/{id?}',[
        'uses' => 'ProjectController@edit'
    ])->name('project.edit');
    //删除项目
    Route::match(['get','post'],'delete/{id}',[
        'uses' => 'ProjectController@delete'
    ])->name('project.delete');
    //成员管理
    Route::get('members/{id}',[
        'as' => 'project.members', 'uses' => 'ProjectController@members'
    ]);
    //添加或删除项目的用户
    Route::post('members/add/{id}',[
        'as' => 'project.members.add', 'uses' => 'ProjectController@addMember'
    ]);
    //退出项目
    Route::post('quit/{id}',[
       'as' => 'project.quit', 'uses' => 'ProjectController@quit'
    ]);
    //转让项目
    Route::post('transfer/{id}')->uses('ProjectController@transfer')->name('project.transfer');

    //加载Calibre项目，从Calibre库中复制zip书籍到系统
    Route::post('calibres/reload', [
        'as' => 'project.calibres.reload', 'uses' => 'ProjectController@reloadCalibres'
    ]);

    //查询Calibre项目
    Route::post('calibres/query/{id}', [
        'as' => 'project.calibres.query', 'uses' => 'ProjectController@queryCalibre'
    ]);

    //删除Calibre项目
    Route::post('calibres/delete/{id}', [
        'as' => 'project.calibres.delete', 'uses' => 'ProjectController@deleteCalibre'
    ]);

    //导入/转化Calibre项目
    Route::post('calibres/import/{id}', [
        'as' => 'project.calibres.import', 'uses' => 'ProjectController@importCalibre'
    ]);

    //导入/转化所有Calibre项目
    Route::post('calibres/importAll', [
        'as' => 'project.calibres.importAll', 'uses' => 'ProjectController@importAllCalibre'
    ]);

    //替换文档内的链接
    Route::get('calibres/replace/{id}', [
        'as' => 'project.calibres.replace', 'uses' => 'ProjectController@replaceCalibreUrl'
    ]);

    //处理pre标签引起的代码块问题
    Route::get('calibres/deal/{id}', [
        'as' => 'project.calibres.deal', 'uses' => 'ProjectController@dealCalibreCode'
    ]);
});

Route::group(['middleware' => 'authorize','prefix' => 'docs'],function (){
    //文档编辑首页
    Route::get('edit/{id}',[
        'as' => 'document.index', 'uses' => 'DocumentController@index'
    ])->where('id', '[0-9]+');

    //编辑文档
    Route::get('content/{id}',[
        'as' => 'document.edit' , 'uses' => 'DocumentController@getContent'
    ])->where('id', '[0-9]+');
    //保存文档
    Route::post('save',[
       'as' => 'document.save', 'uses' => 'DocumentController@save'
    ]);
    //删除文档
    Route::post('delete/{doc_id}',[
       'as' => 'document.delete', 'uses' => 'DocumentController@delete'
    ])->where('doc_id', '[0-9]+');

    Route::post('sort/{doc_id}',[
        'as' => 'document.sort', 'uses' => 'DocumentController@sort'
    ])->where('doc_id', '[0-9]+');

    //查看文档记录
    Route::match(['get','post'],'history/{id}',[
        'uses' => 'DocumentController@history'
    ])->where('id', '[0-9]+')->name('document.history');

    //删除文档记录
    Route::post('history/delete',[
        'uses' => 'DocumentController@deleteHistory'
    ])->name('document.history.delete');

    //恢复文档版本
    Route::post('history/restore',[
        'uses' => 'DocumentController@restoreHistory'
    ])->name('document.history.restore');
});
//文件上传
Route::post('/upload',[
    'middleware' => 'authorize' , 'as' => 'document.upload', 'uses' => 'DocumentController@upload'
]);

//查看文档详情
Route::get('/docs/show/{doc_id}',[
    'as' => 'document.show', 'uses' => 'DocumentController@show'
]);

//处理pre代码块节点
Route::get('/docs/deal/{doc_id}',[
    'as' => 'document.deal', 'uses' => 'DocumentController@deal'
]);

//文档编辑小部件
Route::get('widget/edit-document',[
    'as' => 'widget.editDocument', 'uses' => 'WidgetController@editDocument'
]);

/**
 * 用户中心
 */
Route::group(['middleware' => 'authorize','prefix' => 'member'],function (){
    //修改密码
    Route::match(['get','post'],'account',[
        'uses' => 'MemberController@account'
    ])->name('member.account');
    //我的项目列表
    Route::get('projects',[
        'as' => 'member.projects', 'uses' => 'MemberController@projects'
    ]);
    //Calibre项目列表
    Route::get('calibres', [
        'as' => 'member.calibres', 'uses' => 'MemberController@calibres'
    ]);
    //用户中心
    Route::match(['post','get'],'',[
        'uses' => 'MemberController@index'
    ])->name('member.index');

    Route::post('upload',[
        'as' => 'member.upload', 'uses' => 'MemberController@upload'
    ]);

});
/**
 * 安装首页
 */
Route::match(['get','post'],'/install',[
    'uses' => 'InstallController@next'
])->name('install.index');
