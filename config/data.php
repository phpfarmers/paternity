<?php
return [
    'oss_data_local' => '/akdata/oss_data/', // oss数据目录 样本数据下机目录
    // 'oss_data_remote' => 'oss://skyseq-product/C1830885909785473024/', // 要下载的远程目录-可根据实际情况修改
    'oss_data_remote' => 'oss://sh-kefu-ankang/rawdata/', // 要下载的远程目录-可根据实际情况修改
    'analysis_project' => '/akdata/project/', // 一级分析项目目录
    'second_analysis_project' => '/akdata/second_run_dir/', // 二级分析项目目录
    'sample_analysis_run_command_pl' => '/akdata/script/paternity-test/run_qinzi.pl', // 样本分析命令文件
    'family_analysis_run_command_pl' => '/akdata/script/paternity-test/bin/parse_perbase.pl', // 家系分析命令文件
    'family_synonym_run_command_pl' => '/akdata/script/paternity-test/bin/synonym.pl', // 同一认定
    'family_analysis_run_command_call_r' => '/akdata/script/paternity-test/bin/cal.r', // 家系分析命令文件
    'family_analysis_run_command_default_r' => 4,
    'family_analysis_run_command_default_s' => 0.008,
    'family_analysis_run_command_umi_default_r' => 2,
    'family_analysis_run_command_umi_default_s' => 0.002,
    'perl_path' => 'PATH=/akdata/software/bin:/akdata/software/bin:/akdata/software/bin:/akdata/software/micromamba/condabin:/akdata/software/bin:/home/labserver2/.local/bin:/home/labserver2/bin:/sbin:/bin:/usr/bin:/usr/local/bin:/usr/local/sbin:/usr/sbin',
    'perl_perl5ltb' => 'PERL5LIB=/akdata/software/micromamba/envs/qinzi/lib/perl5/5.32/site_perl:/akdata/software/micromamba/envs/qinzi/lib/perl5/site_perl:/akdata/software/micromamba/envs/qinzi/lib/perl5/5.32/vendor_perl:/akdata/software/micromamba/envs/qinzi/lib/perl5/vendor_perl:/akdata/software/micromamba/envs/qinzi/lib/perl5/5.32/core_perl:/akdata/software/micromamba/envs/qinzi/lib/perl5/core_perl',
    'qc_data_dir' => '/home/labserver2/qcfile/',//样本质控数据目录
];