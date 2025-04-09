<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Collection;

class SampleImport implements ToCollection
{
    /**
     * 将 Excel 文件数据转换为集合
     *
     * @param Collection $rows
     * @return void
     */
    public function collection(Collection $rows)
    {
        return $rows;
    }
}