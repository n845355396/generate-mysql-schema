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
                $tableArr = $this->requireFile($file_path);

                $tableName = $tableArr['table_name'];
                $hasTable  = $this->conn->query("SHOW TABLES LIKE '{$tableName}'");
                if ($hasTable->num_rows != 0) {
                    $this->upTableFields($tableArr);
                    echo "table:$tableName---->update OK\n";
                } else {
                    $res = $this->assemblySql($tableArr);
                    if ($res !== true) {
                        throw new \LogicException($res . ":" . $this->conn->error);
                    }
                    echo "table:$tableName---->add OK\n";
                }
            }
        }
        return true;
    }

    public function upTableFields($tableArr)
    {
        $tableName   = $tableArr['table_name'];
        $columns     = $tableArr['columns'];
        $fieldSqlArr = [];
        foreach ($columns as $field => $info) {
            $resData = $this->conn->query(" desc `{$tableName}` `{$field}`");
            if ($resData->num_rows != 0) {
                $fieldObj = $resData->fetch_object();

                $fieldSql      = $this->fieldInfo($field, $info, $tableArr, true);
                $fieldSqlArr[] = " change `{$field}` " . $fieldSql;

                if ($fieldObj->Key == "PRI" && $fieldObj->Extra == "auto_increment") {
                    $fieldSql = str_replace("AUTO_INCREMENT", "", $fieldSql);
                    $this->conn->query("alter table `{$tableName}` modify {$fieldSql}");
                }
            } else {
                //lpc 不存在的字段直接新增
                $addFieldSql = "alter table {$tableName} add  ";

                $fieldSql    = $this->fieldInfo($field, $info, $tableArr, true);
                $addFieldSql .= " $fieldSql";
                $res         = $this->conn->query($addFieldSql);
                if (!$res) {
                    throw new \LogicException("{$tableName}表下新增字段{$field}失败！:" . $this->conn->error);
                }
            }
        }

        //lpc 更新主键
        $res = $this->conn->query("alter table `{$tableName}` drop primary key");
        if (!$res) {
            throw new \LogicException("{$tableName}:主键删除失败:" . $this->conn->error);
        }
        $primaryKeys = [];
        foreach ($tableArr['primary'] as $val) {
            $primaryKeys[] = "`" . $val . "`";
        }
        $primaryKeys = implode(",", $primaryKeys);
        $res         = $this->conn->query("alter table `{$tableName}` add primary key($primaryKeys)");
        if ($res != 1) {
            throw new \LogicException("更新主键错误！" . $this->conn->error);
        }

        if (count($fieldSqlArr) > 0) {
            $batchUpFieldSql = "alter table `{$tableName}` ";
            $batchUpFieldSql .= implode(",", $fieldSqlArr);
            $res             = $this->conn->query($batchUpFieldSql);
            if ($res !== true) {
                throw new \LogicException("修改表字段出现错误:" . $this->conn->error);
            }
        }

        //更新索引
        $indexRes  = $this->conn->query("show index from `{$tableName}`");
        $indexData = [];
        if ($indexRes->num_rows) {
            while ($data = $indexRes->fetch_object()) {
                if ($data->Key_name != "PRIMARY") {
                    $this->conn->query("drop index {$data->Key_name} on `{$tableName}`");
                }
            }
        }
        foreach ($tableArr['index'] as $key => $value) {
            if (count($value) > 0) {
                $type     = isset($value['type']) ? $value['type'] : "normal";
                $indexArr = [];
                foreach ($value['columns'] as $field) {
                    $indexArr[] = "`" . $field . "`";
                }
                $indexSt   = implode(",", $indexArr);
                $indexType = $type == "unique" ? "UNIQUE" : "";


                $addIndexSql = "CREATE  {$indexType}  INDEX  {$key} ON  {$tableName}($indexSt)";
                $res         = $this->conn->query($addIndexSql);
                if ($res != 1) {
                    throw new \LogicException("更新索引错误！" . $this->conn->error);
                }
            }
        }

        //更新表搜索引擎
        if (isset($tableArr['engine']) && $tableArr['engine']) {
            $res = $this->conn->query("ALTER TABLE {$tableName} ENGINE={$tableArr['engine']}");
            if ($res != 1) {
                throw new \LogicException("更新表搜索引擎错误！" . $this->conn->error);
            }
        }

        //更新表编码
        if (isset($tableArr['charset']) && $tableArr['charset']) {
            $res = $this->conn->query(" alter table {$tableName} convert to character set {$tableArr['charset']}");
            if ($res != 1) {
                throw new \LogicException("更新表编码错误！" . $this->conn->error);
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
            $fieldArr[] = $this->fieldInfo($field, $info, $tableArr, true);
        }

        //@Author: lpc @Description: 加主键 @DateTime: 2020/9/7 14:33
        $primaryKeys = [];
        foreach ($tableArr['primary'] as $val) {
            $primaryKeys[] = "`" . $val . "`";
        }
        $primaryKeys = implode(",", $primaryKeys);
        $fieldArr[]  = "PRIMARY KEY ($primaryKeys)";

        //@Author: lpc @Description: 添加索引 @DateTime: 2020/9/5 20:44
        foreach ($tableArr['index'] as $key => $value) {
            if (count($value) > 0) {
                $type     = isset($value['type']) ? $value['type'] : "";
                $indexArr = [];
                foreach ($value['columns'] as $field) {
                    $indexArr[] = "`" . $field . "`";
                }
                $indexSt    = implode(",", $indexArr);
                $indexType  = $type == "unique" ? "UNIQUE" : "";
                $fieldArr[] = "{$indexType} KEY `{$key}` ({$indexSt})";
            }
        }

        $fieldSql = implode(" , ", $fieldArr);
        $sql      .= "(" . $fieldSql . ")";

        $sql .= $this->charsetInfo($tableArr);

        return $this->dataBase->mysqliCreateTable($sql);
    }

    private function fieldInfo($field, $fieldInfo, $tableArr, $isChange = false)
    {
        $infoArr[] = "`" . $field . "`";

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
        if (in_array($field, $tableArr['primary']) && !$isChange) {
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
