@extends('member')
@section('title')我的项目@endsection
@section('scripts')
    <script type="text/javascript">
        function showError($msg) {
            $("#error-message").addClass("error-message").removeClass("success-message").text($msg);
        }
        function showSuccess($msg) {
            $("#error-message").addClass("success-message").removeClass("error-message").text($msg);
        }
        $(function () {
            $("[data-toggle='tooltip']").tooltip();

            $(".project-quit-btn").on('click',function () {
               var url = $(this).attr('data-url');
               var $then = $(this);
               $then.closest('li').remove().empty();
               if(url){
                    $.post(url,{},function(res){
                        if(res.errcode === 0){
                            $then.closest('li').slideUp(200,function () {
                               $then.remove().empty();
                            });
                        }else{
                            layer.msg(res.message);
                        }
                    },'json');
               }
            });

            $("#btn_reloadCalibres").click(function() {
                $.ajax({
                    url: "/project/calibres/reload",
                    type: "POST",
                    async : false,
                    success: function (data) {
                        if (data) {
                            data.message && alert(data.message);
                            data.success && window.location.reload();
                        }
                    }
                });
            });

            $("#btn_importAllCalibres").click(function(){
                $.ajax({
                    url: "/project/calibres/importAll",
                    type: "POST",
                    async : false,
                    success: function (data) {
                        if (data) {
                            data.message && alert(data.message);
                            data.success && window.location.reload();
                        }
                    }
                });
            });

            $("li a.btn_deleteCalibre").click(function() {
                var calibreId = $(this).closest("li").attr("calibre_id");
                if (calibreId) {
                    $.ajax({
                        url: "/project/calibres/delete/" + calibreId,
                        type: "POST",
                        async : false,
                        success: function (data) {
                            if (data) {
                                data.message && alert(data.message);
                                data.success && window.location.reload();
                            }
                        }
                    });
                }
            });

            $("li a.btn_importCalibre").click(function() {
                var calibreId = $(this).closest("li").attr("calibre_id");
                if (calibreId) {
                    $.ajax({
                        url: "/project/calibres/import/" + calibreId,
                        type: "POST",
                        async : false,
                        success: function (data) {
                            if (data) {
                                data.message && alert(data.message);
                                data.success && window.location.reload();
                            }
                        }
                    });
                }
            });

            $("div.searchbar").show().find("form.form-inline").attr("action", "/member/calibres");
        });
    </script>
@endsection
@section('content')
    <div class="project-box">
        <div class="box-head">
            <h4>Calibre项目列表 </h4>
            &nbsp;&nbsp;&nbsp;&nbsp;
            <span style="padding-left: 10px;">总共：{{$imported + $unImport + $importing}}</span>（
            <span style="color: green;">已导入：{{$imported}}，</span>
            <span style="color: red;">未导入：{{$unImport}}，</span>
            <span style="color: blue;">导入中：{{$importing}}</span>）
            <a href="#" class="btn btn-success btn-sm pull-right" id="btn_importAllCalibres" style="margin: 10px;">导入所有项目</a>

            <a href="#" class="btn btn-success btn-sm pull-right" id="btn_reloadCalibres" style="margin: 10px;">刷新Calibre库</a>
        </div>
        <div class="box-body">
            <div class="error-message">
            </div>
            <div class="project-list">
                <ul>
                @foreach($lists as $item)
                        <li id="li{{$item->calibre_id}}" calibre_id="{{$item->calibre_id}}">
                            <div>
                                <div>
                                    <div class="pull-left">
                                        <span class="hint--bottom" title="公开文档" data-toggle="tooltip" data-placement="bottom">
                                        <i class="fa fa-unlock" title="公开文档"></i>
                                        </span>
                                        @if($item->state == 1)
                                            <a href="{{route('home.show',['id'=>$item->project_id])}}" title="" data-toggle="tooltip" data-placement="bottom" >{{$item->title}}</a>
                                        @else
                                            <a href="#" title="" data-toggle="tooltip" data-placement="bottom" >{{$item->title}}</a>
                                        @endif
                                    </div>
                                    <div class="pull-right">
                                        @if($item->state == 1)
                                            <a href="{{route('home.show',['id'=>$item->project_id])}}" title="查看文档" style="font-size: 12px;" data-toggle="tooltip" data-placement="bottom"  target="_blank"><i class="fa fa-eye"></i> 查看</a>
                                        @else
                                            <a href="#" title="导入项目" class="btn_importCalibre" style="font-size: 12px;" data-toggle="tooltip" data-placement="bottom"><i class="fa fa-upload"></i> 导入</a>
                                        @endif
                                        <a href="#" title="删除项目" class="btn_deleteCalibre" style="font-size: 12px;" data-toggle="tooltip" data-placement="bottom"><i class="fa fa-trash"></i> 删除</a>
                                    </div>
                                    <div class="clearfix"></div>
                                </div>
                                <div class="desc-text">&nbsp;</div>
                                <div class="info">
                                    <span style="display: inline-block;padding-left: 10px;" title="作者" data-toggle="tooltip" data-placement="bottom"><i class="fa fa-user"></i> {{$item->author}}</span>
                                    <span title="出版时间" data-toggle="tooltip" data-placement="bottom"><i class="fa fa-clock-o"></i> {{$item->date}}</span>
                                    @if($item->state == 1)
                                        <span title="已导入" data-toggle="tooltip" data-placement="bottom" style="color:green;"><i class="fa fa-star"></i> 已导入</span>
                                    @elseif($item->state == 2)
                                        <span title="导入中..." data-toggle="tooltip" data-placement="bottom" style="color:blue;"><i class="fa fa-star"></i> 导入中...</span>
                                    @else
                                        <span title="未导入" data-toggle="tooltip" data-placement="bottom" style="color:red;"><i class="fa fa-star-o"></i> 未导入</span>
                                    @endif
                                </div>
                            </div>
                        </li>
                @endforeach
                </ul>
            </div>
        </div>

    </div>
    <div>
        <nav>
            {{$lists->render()}}
        </nav>
    </div>
    <script type="text/javascript" src="{{asset('static/htmlConverter/dist/to-markdown.js')}}"></script>
    <script type="text/javascript" src="{{asset('static/htmlConverter/htmlConverter.js')}}"></script>
@endsection