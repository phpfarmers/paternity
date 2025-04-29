<?php
return [
    'oss_data_local' => '/akdata/oss_data/', // oss数据目录 样本数据下机目录
    'oss_data_remote' => 'oss://skyseq-product/', // 要下载的远程目录-可根据实际情况修改
    'analysis_project' => '/akdata/project/', // 一级分析项目目录
    'second_analysis_project' => '/akdata/second_run_dir/', // 二级分析项目目录
    'sample_analysis_run_command_pl' => '/akdata/script/paternity-test/run_qinzi.pl', // 样本分析命令文件
    'family_analysis_run_command_pl' => '/akdata/script/paternity-test/bin/parse_perbase.pl', // 家系分析命令文件
    'family_analysis_run_command_call_r' => '/akdata/script/paternity-test/bin/cal.r', // 家系分析命令文件
];