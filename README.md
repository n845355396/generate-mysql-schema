# generate-mysql-schema

#### 介绍
2020/9/5：找了一圈没找到用PHP来控制数据库表结构的包，自己尝试写写吧
一个php文件代表一张表，运行时没有表则直接新增，存在的话则更新表结构(我没有判断字段是否改动更新，都是直接全部更新)。

#### 软件架构
今天天气真好~


#### 安装教程

1. 项目里运行: composer require lpc/generate-mysql-schema dev-master


#### 使用说明

1.  将2中的代码放进项目的php文件内，可在控制台执行都行;

2.  项目需要导入一份配置文件
<pre name="code" class="php">
    $info = require "config/database.php";
    $run = new Run($info);
    $res = $run->generate();//返回 true|false
</pre>

3.配置文件 database.php
 <pre name="code" class="php">
        /*
         * @Author: lpc
         * @DateTime: 2020/9/5 18:16
         * @Description: 管理数据库表配置文件
         */
        return [
            //连接数据库信息
            'source_data'    => [
                'connection_type' => "mysqli",
                'host'             => '127.0.0.1',
                'port'             => 3306,
                'username'         => 'root',
                'password'         => 'root',
                'dbname'           => "lpc",
            ],
            //表文件存在的位置
            'dbschema_dir_path'     => [
                "table_path1",
                "table_path2",
            ],
            //自定义表字段类型
            'diy_field_type' => [
                'int'     => 'int',
                'varchar' => 'varchar',
            ]
        ];
    </pre>
  
 4.上面配置文件的表文件格式，table_path1/user.php
 <pre name="code" class="php">
    /*
     * @Author: lpc
     * @DateTime: 2020/9/5 19:02
     * @Description: 表模板
     */
    
    /**
     * type 类型
     * length 类型长度
     * unsigned 是否无符号
     * autoincrement 是否自动增长
     * required  是否必填
     * default  默认值
     * comment  注释
     */
    
    return [
        'columns'    => [
            'user_id' => [
                'type'          => 'int',
                'length'        => 11,
                'unsigned'      => true,
                'autoincrement' => true,
                'comment'       => '用户Id',
            ],
            'name'    => [
                'type'     => 'varchar',
                'length'   => 50,
                'required' => true,
                'default'  => 'lpc',
                'comment'  => '用户名',
            ],
            'sex'     => [
                'type'    => 'int',
                'length'  => 11,
    //            'default' => 0,
    //            'autoincrement' => true,
                'comment' => '用户性别',
            ],
            'age'     => [
                'type'    => 'int',
                'length'  => 11,
                'default' => 18,
                'comment' => '用户年纪',
            ],
        ],
        //主键 多个主键['user_id','name']
        'primary'    => ['user_id', 'sex'],
        //索引
        'index'      => [
            'ind_name' => ['type' => "normal", 'columns' => ['name','sex']],
            'ind_age'  => ['type' => "unique", 'columns' => ['age']],
        ],
        //表名
        'table_name' => 'user2',
        //表注释
        'comment'    => '用户表',
        'engine'     => 'InnoDB',
        'charset'    => 'utf8mb4',
        'collate'    => 'utf8mb4_general_ci'
    
    ];

 </pre>
 
 5 .配置完以上文件，就是直接运行$run->generate();了，就这样。

