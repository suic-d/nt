<?php

namespace App\Helpers;

use Exception;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class ExcelHelper
{
    /**
     * 导入.
     *
     * @param string $filePath
     * @param int    $startRow
     *
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     *
     * @return array|mixed
     */
    public static function import($filePath, $startRow = 1)
    {
        $reader = new Xlsx();
        $reader->setReadDataOnly(true);
        if (!$reader->canRead($filePath)) {
            $reader = new Xls();
            // setReadDataOnly Set read data only 只读单元格的数据，不格式化 e.g. 读时间会变成一个数据等
            $reader->setReadDataOnly(true);

            if (!$reader->canRead($filePath)) {
                throw new Exception('不能读取Excel');
            }
        }

        $spreadsheet = $reader->load($filePath);
        $sheetCount = $spreadsheet->getSheetCount(); // 获取sheet的数量

        // 获取所有的sheet表格数据
        $excleDatas = [];
        $emptyRowNum = 0;
        for ($i = 0; $i < $sheetCount; ++$i) {
            $currentSheet = $spreadsheet->getSheet($i); // 读取excel文件中的第一个工作表
            $allColumn = $currentSheet->getHighestColumn(); // 取得最大的列号
            $allColumn = Coordinate::columnIndexFromString($allColumn); // 由列名转为列数('AB'->28)
            $allRow = $currentSheet->getHighestRow(); // 取得一共有多少行

            $arr = [];
            for ($currentRow = $startRow; $currentRow <= $allRow; ++$currentRow) {
                // 从第1列开始输出
                for ($currentColumn = 1; $currentColumn <= $allColumn; ++$currentColumn) {
                    $val = $currentSheet->getCellByColumnAndRow($currentColumn, $currentRow)->getValue();
                    $arr[$currentRow][] = trim($val);
                }

                // $arr[$currentRow] = array_filter($arr[$currentRow]);
                // 统计连续空行
                if (empty($arr[$currentRow]) && $emptyRowNum <= 50) {
                    ++$emptyRowNum;
                } else {
                    $emptyRowNum = 0;
                }
                // 防止坑队友的同事在excel里面弄出很多的空行，陷入很漫长的循环中，设置如果连续超过50个空行就退出循环，返回结果
                // 连续50行数据为空，不再读取后面行的数据，防止读满内存
                if ($emptyRowNum > 50) {
                    break;
                }
            }

            $excleDatas[$i] = $arr; // 多个sheet的数组的集合
        }

        // 这里我只需要用到第一个sheet的数据，所以只返回了第一个sheet的数据
        $returnData = $excleDatas ? array_shift($excleDatas) : [];

        // 第一行数据就是空的，为了保留其原始数据，第一行数据就不做array_fiter操作；
        return $returnData
        && isset($returnData[$startRow])
        && !empty($returnData[$startRow]) ? array_filter($returnData) : $returnData;
    }
}
