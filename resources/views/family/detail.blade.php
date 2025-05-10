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
    <div class="layui-tab" lay-filter="detailTab" id="sliderTab">
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
                <div id="page_0"></div>
            </div>
            <!-- 胎儿深度图 -->
            <div class="layui-tab-item"></div>
            <!-- 家系图 -->
            <div class="layui-tab-item"></div>
            <!-- 匹配图 -->
            <div class="layui-tab-item"></div>
            <!-- SNP匹配表 -->
            <div class="layui-tab-item">
                <div class="layui-form-item">
                    <button type="button" class="layui-btn layui-btn-primary downloadTableBtn" style="float:right;">
                        下载</button>
                </div>
                <table id="tsvSNPTable" lay-filter="tsvSNPTable">
                    <thead>
                        <tr>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- 数据会通过表格组件自动填充 -->
                    </tbody>
                </table>
                <div id="page_4"></div>
            </div>
            <!-- 总表 -->
            <div class="layui-tab-item"></div>
            <!-- 父本排查 -->
            <div class="layui-tab-item"></div>
            <!-- 同一认定 -->
            <div class="layui-tab-item"></div>
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

            var father_sample = "{{$family['samples'][3]['sample_name']??''}}";
            var mother_sample = "{{$family['samples'][1]['sample_name']??''}}";
            var child_sample = "{{$family['samples'][2]['sample_name']??''}}";
            var slider_s = "{{$family['s']??''}}";
            var slider_r = "{{$family['r']??''}}";

            // 页面加载完成后自动切换到“简单报告”选项卡
            $(document).ready(function() {
                // 切换到第一个选项卡（索引为 0）
                switchTable(0);
            });

            // 监听选项卡切换事件
            element.on('tab(detailTab)', function(data) {
                // 根据选项卡索引执行不同的操作
                switch (data.index) {
                    case 0: // 简单报告
                        switchTable(0);
                        console.log('切换到简单报告');
                        break;
                    case 1: // 胎儿深度图
                        console.log('切换到胎儿深度图');
                        break;
                    case 2: // 家系图
                        switchImage(2);
                        console.log('切换到家系图');
                        break;
                    case 3: // 匹配图
                        switchImage(3);
                        console.log('切换到匹配图');
                        break;
                    case 4: // SNP匹配表
                        switchTable(4);
                        console.log('切换到SNP匹配表');
                        break;
                        // 其他选项卡...
                    default:
                        console.log('切换到其他选项卡');
                        break;
                }
            });
            // 切换表格
            function switchTable(index) {
                let elem = '';
                let url = '';
                let where = {};
                let cols = [];
                switch (index) {
                    case 0:
                        elem = '#tsvTable';
                        url = '{{ route("family.tsv", $family->id) }}';
                        where = {
                            type: 'summary',
                            father_sample: father_sample,
                            child_sample: child_sample,
                            mother_sample: mother_sample,
                            slider_r: slider_r,
                            slider_s: slider_s,
                        };
                        cols = [
                            [{
                                    field: 'Pairs',
                                    title: '父本名称',
                                    sort: false
                                },
                                {
                                    field: 'Site',
                                    title: '有效位点数',
                                    sort: false
                                },
                                {
                                    field: 'A_N',
                                    title: '错配位点数',
                                    sort: false
                                },
                                {
                                    field: 'MismatchRate',
                                    title: '错配率',
                                    sort: false
                                },
                                {
                                    field: 'CPI',
                                    title: '父权值',
                                    sort: false
                                },
                                // {
                                //     field: 'column3',
                                //     title: ' 深度',
                                //     sort: false
                                // },
                                // 根据TSV文件的列数添加更多列
                            ]
                        ];
                        break;
                    case 4:
                        elem = '#tsvSNPTable';
                        url = '{{ route("family.tsv", $family->id) }}';
                        where = {
                            type: 'report',
                            father_sample: father_sample,
                            child_sample: child_sample,
                            mother_sample: mother_sample,
                            slider_r: slider_r,
                            slider_s: slider_s,
                        };
                        cols = [
                            [{
                                    field: 'ID',
                                    title: '检测位点编号',
                                    sort: false
                                },
                                {
                                    field: 'CHR',
                                    title: '染色体',
                                    sort: false
                                },
                                {
                                    field: 'GT_Father',
                                    title: '父本基因型',
                                    sort: false
                                },
                                {
                                    field: 'GT_Mother',
                                    title: '母本基因型',
                                },
                                {
                                    field: 'GT_Baby',
                                    title: '胎儿基因型',
                                },
                                {
                                    field: 'Match',
                                    title: '是否错配',
                                }
                            ]
                        ];
                        break;
                }

                // 初始化表格
                table.render({
                    elem: elem,
                    url: url, // 使用新添加的路由
                    page: true, // 开启分页
                    beforeSend: function(xhr) { 
                        layer.load(2); // 显示加载层
                    },
                    limit: 10, // 每页显示的条数
                    limits: [10, 20, 30], // 每页条数的选择项
                    where: where,
                    cols: cols,
                    id: 'page_' + index,
                    done: function(res, curr, count) {
                        layer.closeAll('loading'); // 关闭加载层
                    },
                });
            }
            // 切换图片
            function switchImage(index) {
                // 根据选项卡索引执行不同的操作
                switch (index) {
                    case 1:
                        // 胎儿深度图
                        break;
                    case 2:
                        getImgData(2);
                        // 家系图
                        break;
                    case 3:
                        getImgData(3);
                        // 谱系图
                        break;
                        // 其他选项卡...
                    default:
                        break;
                }
                // 接口获取图片数据
                function getImgData(index) {
                    let type = '';
                    switch (index) {
                        case 2:
                            type = 'qc';
                            break;
                        case 3:
                            type = 'child';

                        default:
                            break;
                    }
                    $.ajax({
                        type: "get",
                        url: '{{ route("family.pic", $family->id) }}',
                        dataType: "json",
                        beforeSend: function() {
                            layer.load(2); // 显示加载层
                        },
                        data: {
                            type: type,
                            father_sample: father_sample,
                            child_sample: child_sample,
                            mother_sample: mother_sample,
                            slider_r: slider_r,
                            slider_s: slider_s
                        },
                        success: function(data) {
                            layer.closeAll('loading'); // 关闭加载层
                            if (data.code == 0) {
                                // 获取图片数据成功
                                let html = '<div><button type="button" class="layui-btn layui-btn-primary downloadImgBtn" style="float:right">下载</button></div>';
                                html += "<img src ='" + data.data + "' width='800px'>";
                                $('div.layui-tab-item').eq(index).html(html);
                            }
                        },
                        error: function(xhr, status, error) {
                            layer.closeAll('loading'); // 关闭加载层
                            layer.msg('获取图片数据失败');
                        }
                    })
                }
            }
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
                    beforeSend: function(xhr) {
                        layer.load(2); // 显示加载层
                    },
                    dataType: 'json',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') // 从表单中获取 CSRF 令牌
                    },
                    success: function(res) {
                        layer.closeAll('loading'); // 关闭加载层
                        console.log(res);
                        if (res.code == 0) {
                            // 成功
                            // 重置表格数据
                            father_sample = params.father_sample;
                            child_sample = params.child_sample;
                            mother_sample = params.mother_sample;
                            slider_r = params.slider_r;
                            slider_s = params.slider_s;
                            // 重点击第一个选项卡
                            $(document).find('#sliderTab li').first().trigger('click');
                        } else {
                            // 失败
                            layer.msg(res.msg, {
                                icon: 5
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        layer.closeAll('loading'); // 关闭加载层
                        layer.msg('请求失败，请稍后再试', {
                            icon: 5
                        });
                    }
                });
            });

            // 添加下载按钮点击事件
            $(document).on('click', '.downloadImgBtn', function() {
                // 获取图片的URL
                var imageUrl = $(this).parents('.layui-tab-item').find('img').attr('src');
                // 使用 fetch API 获取图片数据
                fetch(imageUrl)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.blob();
                    })
                    .then(blob => {
                        // 创建一个隐藏的<a>标签，并设置其href属性为生成的Blob URL
                        var link = document.createElement('a');
                        link.href = URL.createObjectURL(blob);
                        link.download = 'image.png'; // 设置下载文件的名称

                        // 模拟点击<a>标签以触发下载
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);

                        // 释放Blob URL
                        URL.revokeObjectURL(link.href);
                    })
                    .catch(error => {
                        console.error('Error downloading image:', error);
                        alert('图片下载失败，请检查网络或图片链接是否正确。');
                    });
            });
            // 下载表格
            $(document).on('click', '.downloadTableBtn', async function() {
                try {
                    // 调用下载函数
                    const blob = await downloadProjectExcel();

                    // 创建下载链接
                    const link = document.createElement('a');
                    link.style.display = 'none';
                    link.href = URL.createObjectURL(blob);
                    link.setAttribute('download', 'snp匹配表.xlsx');

                    // 触发下载
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);

                    // 释放 Blob URL
                    URL.revokeObjectURL(link.href);
                } catch (error) {
                    console.error('下载失败:', error);
                    alert('表格下载失败，请稍后重试。');
                }
            });

            // 下载 Excel 文件
            async function downloadProjectExcel() {
                const params = {
                    father_sample: father_sample,
                    child_sample: child_sample,
                };

                // 将参数拼接到 URL 中
                const url = `/family/downloadTable?${new URLSearchParams(params).toString()}`;

                // 发起请求
                const response = await fetch(url, {
                    method: 'GET',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                });

                // 检查响应状态
                if (!response.ok) {
                    throw new Error('网络响应错误');
                }

                // 返回 Blob 数据
                return response.blob();
            }

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