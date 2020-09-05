<?php

namespace GenerateMysqlSchema;

class Run
{
    private $info;//配置信息
    private $conn = null;//数据库连接对象
    private $dataBase;//数据库操作对象

    public function __construct($info)
    {
        $this->info     = $info;
        $this->dataBase = new DataBase($this->info);
        $this->conn     = $this->dataBase->getConnection();
    }


    public function generate()
    {
        if ($this->conn == null) {
            throw new \LogicException("数据库未连接！");
        }
        $dbschema_dir_path = $this->info['dbschema_dir_path'];
        foreach ($dbschema_dir_path as $dir) {
            $query_file = str_replace("//", "/", "$dir/*.php");
            foreach (glob($query_file) as $file_path) {
                $tableArr  = $this->requireFile($file_path);
                $tableName = $tableArr['table_name'];
                $hasTable  = $this->conn->query("SHOW TABLES LIKE '{$tableName}'");
                if ($hasTable) {
                    throw new \LogicException("表已存在");
                } else {
                    $res = $this->assemblySql($tableArr);
                    if ($res !== true) {
                        throw new \LogicException($res);
                    }
                }
            }
        }
    }

    private function assemblySql($tableArr)
    {
        $sql = "CREATE TABLE ";
        $sql .= $tableArr['table_name'] . " ";

        //@Author: lpc @Description: 组装字段 @DateTime: 2020/9/5 19:46
        $fieldArr = [];
        foreach ($tableArr['columns'] as $field => $info) {
            $fieldArr[] = $this->fieldInfo($field, $info, $tableArr);
        }

        //@Author: lpc @Description: 添加索引 @DateTime: 2020/9/5 20:44
        foreach ($tableArr['index'] as $key => $value) {
            if (count($value) > 0) {
                $indexArr = [];
                foreach ($value as $field) {
                    $indexArr[] = "`" . $field . "`";
                }
                $indexSt    = implode(",", $indexArr);
                $fieldArr[] = "KEY `{$key}` ({$indexSt})";
            }
        }

        $fieldSql = implode(" , ", $fieldArr);
        $sql      .= "(" . $fieldSql . ")";

        $sql .= $this->charsetInfo($tableArr);

        $this->dataBase->mysqliCreateTable($sql);
    }

    private function fieldInfo($field, $fieldInfo, $tableArr)
    {
        $infoArr[] = $field;

        $fieldInfo['type'] ?: "varchar";
        if (array_key_exists($fieldInfo['type'], $this->info['diy_field_type'])) {
            $fieldInfo['type'] = $this->info['diy_field_type'][$fieldInfo['type']];
        }
        if (array_key_exists("length", $fieldInfo)) {
            $infoArr[] = $fieldInfo['type'] . "(" . $fieldInfo['length'] . ")";
        } else {
            $infoArr[] = $fieldInfo['type'];
        }
        if (array_key_exists("unsigned", $fieldInfo) && $fieldInfo['unsigned'] === true) {
            $infoArr[] = 'UNSIGNED';
        }
        if (array_key_exists("autoincrement", $fieldInfo) && $fieldInfo['autoincrement'] === true) {
            $infoArr[] = 'AUTO_INCREMENT';
        }
        if (in_array($field, $tableArr['primary'])) {
            $infoArr[] = 'PRIMARY KEY';
        }
        if (array_key_exists("required", $fieldInfo) && $fieldInfo['required'] === true) {
            $infoArr[] = 'NOT NULL';
        }
        if (array_key_exists("default", $fieldInfo)) {
            $infoArr[] = "DEFAULT '" . $fieldInfo['default'] . "'";
        }
        if (array_key_exists("comment", $fieldInfo)) {
            $infoArr[] = "COMMENT '" . $fieldInfo['comment'] . "'";
        }

        return implode(" ", $infoArr);
    }

    private function charsetInfo($tableArr)
    {
        $engine    = isset($tableArr['engine']) ? $tableArr['engine'] : "InnoDB";
        $charset   = isset($tableArr['charset']) ? $tableArr['charset'] : "utf8mb4";
        $collate   = isset($tableArr['collate']) ? $tableArr['collate'] : "utf8mb4_general_ci";
        $charsetSt = " ENGINE={$engine} CHARSET={$charset} COLLATE={$collate}";
        return $charsetSt;
    }

    private function requireFile($file_path)
    {
        return require_once $file_path;
    }


}
