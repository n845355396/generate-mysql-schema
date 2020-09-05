<?php
/*
 * @Author: lpc
 * @DateTime: 2020/9/5 19:33
 * @Description: 数据库操作
 * 
 * @return 

 */

namespace GenerateMysqlSchema;


class DataBase
{
    private $conn = null;//数据库连接对象
    private $info;//配置信息

    public function __construct($info)
    {
        $this->info = $info;
        if ($this->info['source_data']['connection_type'] == "mysqli") {
            $this->connectionMysqli();
        } elseif ($this->info['source_data']['connection_type'] == "pdo") {
            throw new \LogicException("暂未开发");
            $this->connectionPDO();
        } else {
            throw new \LogicException("数据库连接方式mysqli或者pdo");
        }

    }

    public function getConnection()
    {
        return $this->conn;
    }

    public function mysqliCreateTable($sql)
    {
        // 使用 sql 创建数据表
        if ($this->conn->query($sql) === TRUE) {
            return true;
        } else {
            return "创建数据表错误: " . $this->conn->error;
        }
    }

    private function close()
    {
        $conn->close();
    }

    private function connectionMysqli()
    {
        $servername = $this->info['source_data']['host'] . ":" . $this->info['source_data']['port'];
        $username   = $this->info['source_data']['username'];
        $password   = $this->info['source_data']['password'];
        $dbname     = $this->info['source_data']['dbname'];

        // 创建连接
        $this->conn = new \mysqli($servername, $username, $password, $dbname);
        // 检测连接
        if ($this->conn->connect_error) {
            throw new \LogicException("连接失败: " . mysqli_connect_error());
        }

    }

    private function connectionPDO()
    {
        $servername = $this->info['source_data']['host'] . ":" . $this->info['source_data']['port'];
        $username   = $this->info['source_data']['username'];
        $password   = $this->info['source_data']['password'];
        try {
            $this->conn = new PDO("mysql:host=$servername;", $username, $password);
        } catch (PDOException $e) {
            throw new \LogicException("连接失败: " . $e->getMessage());
        }
    }
}