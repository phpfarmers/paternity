@extends('layouts.app')

@section('content')
<style>
    .layui-table-cell {
        height: auto !important;
        overflow: visible !important;
        white-space: normal !important;
        text-overflow: inherit !important;
    }
</style>
<div class="layui-container">
    <div class="layui-tab">
        <h1>样本信息列表</h1>
    </div>

    <form class="layui-form" id="searchForm" style="margin-bottom: 20px;">
        <div class="layui-form-item">
            <div class="layui-inline">
                <label class="layui-form-label">下机时间：</label>
                <div class="layui-input-inline">
                    <input type="text" name="off_machine_time" id="off_machine_time" class="layui-input" placeholder="下机时间" value="{{ request('off_machine_time') }}">
                </div>
            </div>
            <div class="layui-inline">
                <label class="layui-form-label">分析时间：</label>
                <div class="layui-input-inline">
                    <input type="text" name="analysis_time" id="analysis_time" class="layui-input" placeholder="分析时间" value="{{ request('analysis_time') }}">
                </div>
            </div>
            <div class="layui-inline">
                <label class="layui-form-label">报告时间：</label>
                <div class="layui-input-inline">
                    <input type="text" name="report_time" id="report_time" class="layui-input" placeholder="报告时间" value="{{ request('report_time') }}">
                </div>
            </div>
            <div class="layui-inline">
                <label class="layui-form-label">检测状态：</label>
                <div class="layui-input-inline">
                    <select name="check_result" class="layui-select" lay-ignore style="width: 190px;">
                        <option value="">请选择</option>
                        @foreach($check_result_map_names as $key => $value)
                            <option value="{{ $key }}">{{ $value }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="layui-inline">
                <label class="layui-form-label">分析状态：</label>
                <div class="layui-input-inline">
                    <select name="analysis_result" class="layui-select" lay-ignore style="width: 190px;">
                        <option value="">请选择</option>
                        @foreach($analysis_result_map_names as $key => $value)
                            <option value="{{ $key }}">{{ $value }}</option>
                        @endforeach
                    </select> 
                </div>
            </div>
            <div class="layui-inline">
                <button type="button" class="layui-btn" id="searchBtn">搜索</button>
            </div>
            <!-- 重置 -->
            <div class="layui-inline">
                <button type="reset" class="layui-btn layui-btn-primary">重置</button>
            </div>
            <div class="layui-inline">
                <button type="button" class="layui-btn" id="importBtn">导入样本Excel</button>
            </div>
        </div>
    </form>

    <table id="familyTable" lay-filter="familyTable"></table>

    <script>
        layui.use(['layer', 'upload', 'laydate' , 'jquery', 'form', 'table'], function(){
            var layer = layui.layer;
            var upload = layui.upload;
            var laydate = layui.laydate;
            var $ = layui.jquery;
            var form = layui.form;
            var table = layui.table;

            // 初始化表格
            table.render({
                elem: '#familyTable',
                url: '{{ route("family.table-data") }}',
                method: 'GET',
                page: true,
                limit: 10,
                limits: [10, 20, 50, 100],
                cols: [[
                    {field: 'name', width:140, title: '家系名称'},
                    {field: 'samples', width:160, title: '家系成员', templet: function(d){
                        var samples = '';
                        layui.each(d.samples, function(index, sample){
                            samples += '<div>' + sample.sample_name + '</div>';
                        });
                        return samples;
                    }},
                    {field: 'sample_type', width:80, title: '称谓', templet: function(d){
                        var sample_type = '';
                        layui.each(d.samples, function(index, sample){
                            sample_type += '<div>' + sample.sample_type_name + '</div>';
                        });
                        return sample_type;
                    }},
                    {field: 'off_machine_time', title: '下机时间', templet: function(d){
                        var off_machine_time = '<div>';
                        layui.each(d.samples, function(index, sample){
                            if(sample.check_result == 2){
                                off_machine_time += '<span class="layui-badge layui-bg-green">已检测</span>' + sample.off_machine_time ;
                            }else if(sample.check_result == 1){
                                off_machine_time += '<span class="layui-badge layui-bg-orange">检测中</span>' ;
                            }else if(sample.check_result == 3){
                                off_machine_time += '<span class="layui-badge layui-bg-blue">检测失败</span>';
                                off_machine_time += ' <a class="layui-btn layui-btn-xs sample-check-rerun" data-sample-id="'+sample.id+'">重新检测</a>';
                            }else{
                                off_machine_time += '<span class="layui-badge layui-bg-red">未检测</span>';
                                off_machine_time += ' <a class="layui-btn layui-btn-xs sample-check-run" data-sample-id="'+sample.id+'">检测</a>';
                            }
                        });
                        off_machine_time += '</div>';
                        return off_machine_time;
                    }},
                    {field: 'off_machine_data', width:100, title: '下机数据量', templet: function(d){
                        var off_machine_data = '';
                        layui.each(d.samples, function(index, sample){
                            off_machine_data += '<div>' + sample.off_machine_data + '</div>';
                        });
                        return off_machine_data;
                    }},
                    {field: 'analysis_time', title: '分析时间', templet: function(d){
                        var analysis_time = '';
                        layui.each(d.samples, function(index, sample){
                            if(sample.analysis_result == 2){
                                analysis_time += '<div><span class="layui-badge layui-bg-green">已分析</span>' + sample.analysis_time + '</div>';
                            }else if(sample.analysis_result == 1){
                                analysis_time += '<div><span class="layui-badge layui-bg-orange">分析中</span></div>' ;
                            }else if(sample.analysis_result == 3){
                                analysis_time += '<div><span class="layui-badge layui-bg-blue">分析失败</span>';
                                analysis_time += ' <a class="layui-btn layui-btn-xs sample-analysis-rerun" data-sample-id="'+sample.id+'">重新分析</a></div>';
                            }else{
                                analysis_time += '<div><span class="layui-badge layui-bg-red">未分析</span>';
                                analysis_time += ' <a class="layui-btn layui-btn-xs sample-analysis-run" data-sample-id="'+sample.id+'">分析</a></div>';
                            }
                        });
                        
                        return analysis_time;
                    }},
                    {field: 'report_time', title: '报告时间', templet: function(d){
                        var report_time = '';
                        if(d.report_result == 2){
                            report_time += '<div><span class="layui-badge layui-bg-green">已分析</span>' + d.report_time + '</div>';
                        }else if(d.report_result == 1){
                            report_time += '<div><span class="layui-badge layui-bg-orange">分析中</span></div>' ;
                        }else if(d.report_result == 3){
                            report_time += '<div><span class="layui-badge layui-bg-blue">分析失败</span>';
                            report_time += ' <a class="layui-btn layui-btn-xs family-analysis-rerun" data-family-id="'+d.id+'">重新分析</a></div>';
                        }else{
                            report_time += '<div><span class="layui-badge layui-bg-red">未分析</span>';
                            report_time += ' <a class="layui-btn layui-btn-xs family-analysis-run" data-family-id="'+d.id+'">分析</a></div>';
                        }
                        
                        return report_time;
                    }},
                    {field: 'report_result', title: '报告解读', templet: function(d){
                        if(d.report_result == 2){
                            return '<a href="{{ route("family.detail") }}?id=' + d.id + '" class="layui-btn layui-btn-xs">进入</a>';
                        } else {
                            return '<span class="layui-badge layui-bg-red">未解读</span>';
                        }
                    }}
                ]]
            });

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
            // 初始化上传组件
            upload.render({
                elem: '#importBtn',
                url: '{{ route("sample.import") }}', // 导入的URL
                accept: 'file', // 允许上传的文件类型
                exts: 'xls|xlsx', // 允许的文件后缀
                done: function(res){
                    if(res.code === 0){
                        layer.msg('导入成功', {icon: 1});
                        setTimeout(function(){
                            window.location.reload();
                        }, 1000);
                    } else {
                        layer.msg(res.message, {icon: 2});
                    }
                },
                error: function(){
                    layer.msg('导入失败，请稍后重试', {icon: 2});
                }
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
            // 初始化日期选择器-分析时间选择器
            laydate.render({
                elem: '#analysis_time',
                type: 'date',
                range: true,
                done: function(value, date, endDate){
                    // 设置分析时间范围
                    $('input[name="analysis_time"]').val(value);
                }
            });
            // 初始化日期选择器-报告时间选择器
            laydate.render({
                elem: '#report_time',
                type: 'date',
                range: true,
                done: function(value, date, endDate){
                    // 设置报告时间范围
                    $('input[name="report_time"]').val(value);
                }
            });
            // 样本检测运行按钮点击事件
            $(document).on('click', '.sample-check-run', function(){
                var sampleId = $(this).data('sample-id');
                layer.confirm('确定要运行样本检测吗？', {
                    btn: ['确定', '取消'],
                    title: '运行样本检测'
                }, function(){
                    $.ajax({
                        url: '{{ route("sample.checkRun") }}',
                        type: 'get',
                        data: {
                            sample_id: sampleId
                        },
                        dataType: 'json',
                        beforeSend: function(){
                            layer.load(2);
                        },
                        success: function(res){
                            layer.closeAll();
                            if(res.code === 0){
                                layer.msg('运行成功', {icon: 1});
                                setTimeout(function(){
                                    window.location.reload();
                                }, 1000);
                            } else {
                                layer.msg(res.message, {icon: 2});
                            }
                        },
                        error: function(){  // 请求失败
                            layer.msg('运行失败，请稍后重试', {icon: 2});
                            layer.closeAll();
                        }
                    })
                }
                )
            });
            // 样本检测重新运行按钮点击事件
            $(document).on('click', '.sample-check-rerun', function(){
                var sampleId = $(this).data('sample-id');
                layer.confirm('确定要重新运行样本检测吗？', {
                    btn: ['确定', '取消'],
                    title: '重新运行样本检测'
                }, function(){
                    $.ajax({
                        url: '{{ route("sample.checkRerun") }}',
                        type: 'get',
                        data: {
                            sample_id: sampleId
                        },
                        dataType: 'json',
                        beforeSend: function(){
                            layer.load(2);
                        },
                        success: function(res){
                            layer.closeAll();
                            if(res.code === 0){
                                layer.msg('重新运行成功', {icon: 1});
                                setTimeout(function(){
                                    window.location.reload();
                                }, 1000);
                            } else {
                                layer.msg(res.message, {icon: 2});
                            }
                        },
                        error: function(){  // 请求失败 
                            layer.msg('重新运行失败，请稍后重试', {icon: 2});
                            layer.closeAll();
                        }
                    })
                }
                )
            });
            // 样本分析运行按钮点击事件
            $(document).on('click', '.sample-analysis-run', function(){
                var sampleId = $(this).data('sample-id');
                layer.confirm('确定要运行样本分析吗？', {
                    btn: ['确定', '取消'],
                    title: '运行样本分析'
                }, function(){
                    $.ajax({
                        url: '{{ route("sample.analysisRun") }}',
                        type: 'get',
                        data: {
                            sample_id: sampleId
                        },
                        dataType: 'json',
                        beforeSend: function(){
                            layer.load(2);
                        },
                        success: function(res){
                            layer.closeAll();
                            if(res.code === 0){
                                layer.msg('运行成功', {icon: 1});
                                setTimeout(function(){
                                    window.location.reload();
                                }, 1000);
                            }else{
                                layer.msg(res.message, {icon: 2});
                            }
                        },
                        error: function(){  // 请求失败
                            layer.msg('运行失败，请稍后重试', {icon: 2});
                            layer.closeAll();
                        }
                    })
                }
                )
            });
            // 样本分析重新运行按钮点击事件
            $(document).on('click', '.sample-analysis-rerun', function(){
                var sampleId = $(this).data('sample-id');
                layer.confirm('确定要重新运行样本分析吗？', {
                    btn: ['确定', '取消'],
                    title: '重新运行样本分析'
                }, function(){
                    $.ajax({
                        url: '{{ route("sample.analysisRerun") }}',
                        type: 'get',
                        data: {
                            sample_id: sampleId
                        },
                        dataType: 'json',
                        beforeSend: function(){
                            layer.load(2);
                        },
                        success: function(res){
                            layer.closeAll();
                            if(res.code === 0){
                                layer.msg('重新运行成功', {icon: 1});
                                setTimeout(function(){
                                    window.location.reload();
                                }, 1000);
                            }else{
                                layer.msg(res.message, {icon: 2});
                            }
                        },
                        error: function(){  // 请求失败 
                            layer.msg('重新运行失败，请稍后重试', {icon: 2});
                            layer.closeAll();
                        }
                    })
                }
                )
            });
            // 报告分析运行按钮点击事件
            $(document).on('click', '.family-analysis-run', function(){
                var familyId = $(this).data('family-id');
                layer.confirm('确定要运行报告分析吗？', {
                    btn: ['确定', '取消'],
                    title: '运行报告分析'
                }, function(){
                    $.ajax({
                        url: '{{ route("family.analysisRun") }}',
                        type: 'get',
                        data: {
                            family_id: familyId
                       },
                        dataType: 'json',
                        beforeSend: function(){
                            layer.load(2);
                        },
                        success: function(res){
                            layer.closeAll();
                            if(res.code === 0){
                                layer.msg('运行成功', {icon: 1});
                                setTimeout(function(){
                                    window.location.reload();
                                }, 1000);
                            }else{
                                layer.msg(res.message, {icon: 2});
                            }
                        },
                        error: function(){  // 请求失败 
                            layer.msg('运行失败，请稍后重试', {icon: 2});
                            layer.closeAll();
                        }
                    })
                }
                )
            });
            // 报告分析重新运行按钮点击事件
            $(document).on('click', '.family-analysis-rerun', function(){
                var familyId = $(this).data('family-id');
                layer.confirm('确定要重新运行报告分析吗？', {
                    btn: ['确定', '取消'],
                    title: '重新运行报告分析'
                }, function(){
                    $.ajax({
                        url: '{{ route("family.analysisRerun") }}',
                        type: 'get',
                        data: {
                            family_id: familyId
                        },
                        dataType: 'json',
                        beforeSend: function(){
                            layer.load(2);
                        },
                        success: function(res){
                            layer.closeAll();
                            if(res.code === 0){
                                layer.msg('重新运行成功', {icon: 1});
                                setTimeout(function(){
                                    window.location.reload();
                                }, 1000);
                            }else{
                                layer.msg(res.message, {icon: 2});
                            }
                        },
                        error: function(){  // 请求失败 
                            layer.msg('重新运行失败，请稍后重试', {icon: 2});
                            layer.closeAll();
                        }
                    })
                }
                )
            });
        });
    </script>
@endsection