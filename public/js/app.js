require('./bootstrap');
// 使用Layui模块化方式
layui.use(['layer', 'form'], function(){
    var layer = layui.layer;
    var form = layui.form;

    // 页面加载完成后执行
    document.addEventListener('DOMContentLoaded', function() {
        // 获取所有按钮元素
        const buttons = document.querySelectorAll('.layui-btn');

        // 为每个按钮添加点击事件
        buttons.forEach(button => {
            button.addEventListener('click', function() {
                layer.msg('按钮被点击了！');
            });
        });
    });
});