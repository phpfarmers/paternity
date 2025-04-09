@extends('layouts.app')

@section('content')
<div class="layui-container">
    <div class="layui-tab">
        <h1>家系详情:{{$family->name}}</h1>
    </div>
    <ul class="clear">
        @foreach($family->samples as $sample)
        <li>
            <div>
                <span class="tit">{{$sample->sample_type_name}}：</span>
                <span>{{$sample->sample_name}}</span>
            </div>
        </li>
        @endforeach
    </ul>
    
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
            <div class="layui-tab-item layui-show">内容1</div>
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
        layui.use(['layer', 'upload', 'laydate' , 'jquery', 'form', 'table', 'element'], function(){
            var layer = layui.layer;
            var upload = layui.upload;
            var laydate = layui.laydate;
            var $ = layui.jquery;
            var form = layui.form;
            var table = layui.table;
            var element = layui.element;


            // 搜索按钮点击事件
            $('#searchBtn').on('click', function() {
                var formData = $('#searchForm').serializeArray();
                var params = {};
                $.each(formData, function(i, field){
                    params[field.name] = field.value;
                });
                table.reload('familyTable', {
                    where: params
                });
            });
            
            // 初始化日期选择器-下机时间选择器
            laydate.render({
                elem: '#off_machine_time',
                type: 'date',
                range: true,
                done: function(value, date, endDate){
                    // 设置下机时间范围
                    $('input[name="off_machine_time"]').val(value);
                }
            });
            
        });
    </script>
@endsection