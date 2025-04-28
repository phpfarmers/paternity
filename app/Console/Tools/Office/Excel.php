<?php

namespace App\Console\Tools\Office;

use App\Exceptions\ApiException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class Excel
{
    private $_columns = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z');
    private $_next_columns = array('A', 'AA', 'BA', 'CA', 'DA');
    private $_column_titles = array();

    private $_save_path;
    /**
     * 初始化构造函数
     *
     **/
    public function __construct($params = array())
    {
        if (!empty($params['save_path'])) {
            $this->_save_path = $params['save_path'];
        }
    }

    /**
     * 生成excel(服务器端产生结果文件到磁盘)
     *
     * @param array
     * @param array
     * @param array
     *
     * @return string
     **/
    public function generateXls($infos = [], $header = [], $keys = [])
    {
        if (empty($this->_save_path)) {
            throw new ApiException(1,'请设置保存路径');
        }
        $sheet_api = $this->gererateXls($infos, $header, $keys);

        $writer = IOFactory::createWriter($sheet_api, 'Xlsx');
        $writer->save($this->_save_path);

        return $this->_save_path;

        //测试内容
        /*$header = ['订单编号', '商品总数', '收货人', '联系电话', '收货地址'];
        //keys用于匹配infos里面的key
        $keys = ['order_sn', 'num', 'consignee', 'phone', 'detail'];

        $infos = array(
            array(
                'order_sn' => 'abc',
                'num'      => '123',
                'consignee' => '张三',
                'phone'     => '12345678',
                'detail'    => '上海市浦东新区',
            ),
        );*/
    }

    /**
     * 生成
     *
     * @param array
     * @param array
     * @param array
     *
     * @return Spreadsheet
     **/
    private function gererateXls($infos = [], $header = [], $keys = [])
    {
        if (empty($keys) || empty($header)) {
            throw new  ApiException(1, '请设置表头和keys');
        }
        $header_count = count($header);
        $columns = $this->generateColumn($header_count + 5);

        $sheet_api = new Spreadsheet();
        $sheet = $sheet_api->getActiveSheet();

        for ($i = 65; $i < $header_count + 65; $i++) {
            $sheet->setCellValue($columns[$i - 65] . '1', $header[$i - 65]);
        }

        foreach ($infos as $key => $val) {
            for ($i = 65; $i < $header_count + 65; $i++) {
                if (isset($val[$keys[$i - 65]])) {
                    $sheet->setCellValue($columns[$i - 65] . ($key + 2), $val[$keys[$i - 65]]);
                    $sheet_api->getActiveSheet()->getColumnDimension($columns[$i - 65])->setWidth(20);
                }
            }
        }

        return $sheet_api;
    }

    /**
     * 生成表格列
     *
     * @param int
     * @param string
     *
     * @return array
     **/
    private function generateColumn($number, $string = '')
    {
        if ($number < 1) {
            return array();
        }
        foreach ($this->_columns as $key => $column) {
            if ($number > $key) {
                $this->_column_titles[] = $string . $column;
            }
        }
        $number -= 26;
        $column_index = '';
        if ($number > 1) {
            if (!empty($string)) {
                $column_index = array_search(substr($string, -1), $this->_columns);
                $string = substr($string, 0, -1) . $this->_columns[(++$column_index) % 26];
            } else if (empty($string)) {
                $string = 'A';
            }
            if ($column_index !== '' && $column_index == 26) {
                $next_column_index = array_search($string, $this->_next_columns);
                $string = $this->_next_columns[++$next_column_index];
            }

            $this->generateColumn($number, $string);
        }

        return $this->_column_titles;
    }
}
