@extends('layouts.app')

@section('content')
<div class="layui-container">
    <div class="layui-row">
        <div class="layui-col-md12">
            &nbsp;
        </div>
    </div>
    <div class="layui-row">
        <!-- 左侧 div -->
        <div class="layui-col-md7">
            <div class="layui-tab">
                <h1>家系详情:{{$family->name}}</h1>
            </div>
        </div>
        <!-- 右侧 div -->
        <div class="layui-col-md5">
            <div class="layui-form">
                <form id="searchForm" class="layui-form">
                    <div class="layui-form-item">
                        <label class="layui-form-label">父本编号：</label>
                        <div class="layui-input-block">
                            <input type="text" name="father_sample" placeholder="请输入父本编号" value="{{$family['samples'][3]['sample_name']??''}}" class="layui-input">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">母本编号：</label>
                        <div class="layui-input-block">
                            <input type="text" name="mother_sample" placeholder="请输入母本编号" value="{{$family['samples'][1]['sample_name']??''}}" class="layui-input">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">胎儿编号：</label>
                        <div class="layui-input-block">
                            <input type="text" name="child_sample" placeholder="请输入胎儿编号" value="{{$family['samples'][2]['sample_name']??''}}" class="layui-input">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">参数滑窗：</label>
                        <div class="layui-input-block">
                            <div class="layui-input-inline" style="width: 40%;">
                                <input type="text" name="slider_s" id="sliderValue" placeholder="请输入数值" value="{{$family['s']??''}}" class="layui-input">
                            </div>
                            <div class="layui-input-inline" style="width: 40%;">
                                <input type="text" name="slider_r" placeholder="请输入数值" value="{{$family['r']??''}}" class="layui-input">
                            </div>
                        </div>
                        <!-- <div class="layui-input-inline" style="width: 200px;">
                            <div id="slider" class="layui-slider"></div>
                        </div> -->
                    </div>
                    <div class="layui-form-item">
                        <div class="layui-input-block">
                            <button type="button" id="searchBtn" class="layui-btn">筛选</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- tabs -->
    <div class="layui-tab">
        <ul class="layui-tab-title">
            <li class="layui-this">简单报告</li>
            <li>胎儿深度图</li>
            <li>家系图</li>
            <li>匹配图</li>
            <li>SNP匹配表</li>
            <li>总表</li>
            <li>父本排查</li>
            <li>同一认定</li>
        </ul>
        <div class="layui-tab-content">
            <div class="layui-tab-item layui-show">
                <!-- tsv表格 tsvTable -->
                <table id="tsvTable" lay-filter="tsvTable">
                    <thead>
                        <tr>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- 数据会通过表格组件自动填充 -->
                    </tbody>
                </table>
                <div id="page"></div>
            </div>
            <div class="layui-tab-item">内容2</div>
            <div class="layui-tab-item">内容3</div>
            <div class="layui-tab-item">内容4</div>
            <div class="layui-tab-item">内容5</div>
            <div class="layui-tab-item">内容6</div>
            <div class="layui-tab-item">内容7</div>
            <div class="layui-tab-item">内容8</div>
        </div>
    </div>

    <script>
        layui.use(['layer', 'upload', 'laydate', 'jquery', 'form', 'table', 'element', 'slider'], function() {
            var layer = layui.layer;
            var upload = layui.upload;
            var laydate = layui.laydate;
            var $ = layui.jquery;
            var form = layui.form;
            var table = layui.table;
            var element = layui.element;
            var slider = layui.slider;

            // 初始化表格
            table.render({
                elem: '#tsvTable',
                url: '{{ route("family.tsv", $family->id) }}', // 使用新添加的路由
                page: true, // 开启分页
                limit: 10, // 每页显示的条数
                limits: [10, 20, 30], // 每页条数的选择项
                cols: [
                    [{
                            field: 'column1',
                            title: '父本名称',
                            sort: false
                        },
                        {
                            field: 'column2',
                            title: '有效位点数',
                            sort: false
                        },
                        {
                            field: 'column3',
                            title: '错配位点数',
                            sort: false
                        },
                        {
                            field: 'column3',
                            title: '错配率',
                            sort: false
                        },
                        {
                            field: 'column3',
                            title: '父权值',
                            sort: false
                        },
                        {
                            field: 'column3',
                            title: ' 深度',
                            sort: false
                        },
                        // 根据TSV文件的列数添加更多列
                    ]
                ],
                parseData: function(res) {
                    // 解析TSV文件数据
                    return {
                        "code": 0,
                        "msg": "",
                        "count": res.length,
                        "data": res
                    };
                }
            });
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            // 搜索按钮点击事件
            $('#searchBtn').on('click', function() {
                var formData = $('#searchForm').serializeArray();
                var params = {};
                $.each(formData, function(i, field) {
                    params[field.name] = field.value;
                });
                // 提交接口
                $.ajax({
                    url: '{{ route("family.search", $family->id) }}',
                    type: 'get',
                    data: params,
                    dataType: 'json',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') // 从表单中获取 CSRF 令牌
                    },
                    success: function(res) {
                        console.log(res);
                        if (res.code == 0) {
                            // 成功
                        }else {
                            // 失败
                            layer.msg(res.msg, { icon: 5 });
                        }
                    }
                });
                return;
                table.reload('tsvTable', {
                    // url: '{{ route("family.search", $family->id) }}', // 使用新添加的路由
                    where: params,

                });
            });

            // 初始化滑窗
            slider.render({
                elem: '#slider',
                min: 0,
                max: 100,
                value: 0.03, // 默认值
                // showstep:true,
                step: 1,
                // range: true, // 开启范围选择   
                // 选择范围时的回调             
                change: function(value) {
                    $('#sliderValue').val(value); // 滑窗拖动时更新输入框的值
                }
            });

            // 输入框值变化时更新滑窗
            $('#sliderValue').on('input', function() {
                var value = $(this).val();
                slider.setValue('#slider', value);
            });
        });
    </script>
    @endsection