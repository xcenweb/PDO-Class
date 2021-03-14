<?php
class db_mysql
{
    /**
     * pdo mysql 数据库操作类(需要pdo_mysql扩展)
     * 
     * @Version: 1.0.0
     * @DateTime: 2021/03/14 09:15
     * @Author: ITxcen(落魄群主)
     * @E-mail: 1610943675@qq.com
     *
     * @ps:此版本为长期维护版本哦
     */
	protected $pdo;
	function __construct($dbhost, $port=3306, $dbuser, $dbpw, $dbname, $charset = "utf8")
	{
		try {
			$this->pdo = new PDO("mysql:host={$dbhost};port={$port};dbname={$dbname};charset={$charset}", $dbuser, $dbpw);
			$this->pdo->exec("SET NAMES {$charset}");
		} catch (PDOException $e) {
			die($e->getMessage());
		}
	}
	//========
	public function escape_field($field)
	{
		return str_replace(array("\n", "\r"), array('\\n', '\\r'), $field);
	}
	public function fetch_row($res)
	{
		return $res->fetch(PDO::FETCH_NUM);
	}
	public function fetch_array($query)
	{
		return $query->fetch(PDO::FETCH_ASSOC);
	}
	public function num_rows($query)
	{
		$row = $query->fetch(PDO::FETCH_BOTH);
		return $row[0];
	}
	//========
	public function GetOne($t, $field=null, $condition=null) {
        $param_num = count(func_get_args());
        if ($param_num==1) {
            //走 SQL 语句
            $result = $this->query($param_array[0]);
            $field_obj = $result->fetch_fields();
            if (count($field_obj)>1) {
                die("只能有一个字段");
            } else {
                $f = $field_obj[0]->name;
                $data = '';
                while (!!$row=$result->fetch_assoc()) {
                    $data = $row[$f];
                }
            }
            return $data;
        } elseif ($param_num==3) {
            //走拼接 SQL
            if (substr_count($field, ',')) die("===");
            $sql = "SELECT `{$field}` FROM `{$t}` WHERE {$condition}";
            $result = $this->query($sql);
            while (!!$row=$result->fetch_row()) {
                $r = $row;
            }
            foreach ($r as $v) {
                $data = $v;
            }
            return $data;
        } else {
            exit("参数多了?");
        }
    }
	public function GetRow($tName, $fields="*", $condition='') {
	    //获取一行
        $param_num = count(func_get_args());
        switch ($param_num) {
            case 1:
                if (!is_string($tName)) exit($this->getError(__FUNCTION__, __LINE__));
                $sql = $tName;
                break;
            case 3:
                if (!is_string($tName) || !is_string($condition) || !is_string($fields)) exit($this->getError(__FUNCTION__, __LINE__));
                $sql = "SELECT {$fields} FROM {$tName} WHERE {$condition} LIMIT 1";
                break;
            default:
                exit("参数多了?");
                break;
        }
        return $this->fetch_array($this->query($sql));
    }
	public function Update($tName, $fieldVal=null, $condition=null) {
	    //更新
        $args_count = count(func_get_args());
        switch ($args_count) {
            case 1:
                //直接干数据库
                if (!is_string($tName)) exit("参数错误");
                $sql = $tName;
                break;
            case 3:
                //洗干净，毛理顺再干
                if (!is_array($fieldVal) || !is_string($tName) || !is_string($condition)) exit("参数错误");
                $upStr = '';
                foreach ($fieldVal as $k=>$v) {
                    $upStr .= $k . '=' . '\'' . $v . '\'' . ',';
                }
                $upStr = rtrim($upStr, ',');
                $sql = "UPDATE {$tName} SET {$upStr} WHERE {$condition}";
                break;
            default:
                exit("参数多了?");
        }
        return $this->query($sql);
    }
	public function Insert($tName,$field=array(),$val=array(),$is_lastInsertId=false) {
	    //插入一行
        $field = $this->formatArr($field);
        $val = $this->formatArr($val,false);
        $sql = "INSERT INTO `{$tName}` ({$field}) VALUES ({$val})";
        if($is_lastInsertId){
            return $this->query($sql)->lastInsertId();
        }else{
            return $this->query($sql);
        }
    }
    public function Del($tName, $condition=null) {
        //删除
        $args_count = count(func_get_args());
        switch ($args_count) {
            case 1:
                if (!is_string($tName)) exit("参数错误");
                $sql = $tName;
                break;
            case 2:
                if (!is_string($tName) || !is_string($condition)) exit($this->getError(__FUNCTION__, __LINE__));
                $sql = "DELETE FROM {$tName} WHERE {$condition}";
                break;
        }
        return $this->query($sql);
    }
    //=======
	public function FetchAll($tName, $fields='*', $condition='', $order='', $limit='')
	{
	    //获得所有行
        $param_num = count(func_get_args());
        $space_count = substr_count($tName, ' ');
        $sql = '';
        if ($param_num==1 && $space_count>0) {
            $sql = $tName;
        } else {
            if (!is_string($tName) || !is_string($fields) || !is_string($condition) || !is_string($order) || (!is_string($limit) && !is_int($limit))) exit($this->getError(__FUNCTION__, __LINE__));
	        $fields = ($fields=='*' || $fields=='') ? '*' : $fields;
            $condition = $condition=='' ? '' : " WHERE ". $condition ;
            $order = empty($order) ? '' : " ORDER BY ". $order;
            $limit = empty($limit) ? '' : " LIMIT ". $limit;
            $sql = "SELECT {$fields} FROM {$tName} {$condition} {$order} {$limit}";
	    }
		$res = $this->query($sql);
		if ($res !== false) {
			$arr = array();
			while ($row = $this->fetch_array($res)) {
				$arr[] = $row;
			}
			return $arr;
		} else {
			return false;
		}
	}
	public function Total($tName, $condition='') {
	    //计数，依托GetRow方法
        $param_num = count(func_get_args());
        switch ($param_num) {
            case 1:
                if (!is_string($tName)) exit("参数错误");
                if (substr_count($tName, ' ')) {
                    //SQL语句
                    if (preg_match('/\s+as\s+total\s+/Usi', $tName, $arr)) {
                        $sql = $tName;
                    } else {
                        exit("语句拼接出错");
                    }
                    $sql = $tName;
                } else {
                    $sql = "SELECT COUNT(*) as total FROM {$tName}";
                }
                break;
            case 2:
                if (!is_string($tName) || !is_string($condition)) exit("参数错误");
                $sql = "SELECT COUNT(*) as total FROM {$tName} WHERE " . $condition;
                break;
            default:
                exit("参数多了?");
                break;
        }
        if (!is_string($tName)) exit("参数错误");
        $result = $this->GetRow($sql);
        return $result['total'];
    }
	public function IsExists($tName, $condition) {
	    //是否存在，依托total方法，有数据返回true，反之返回false
        if (!is_string($tName) || !is_string($condition)) exit("参数错误");
        if ($this->Total($tName, $condition)) {
            return true;
        } else {
            return false;
        }
     }
    //=======
    public function executeSQLfile($file=""){
        //执行 .sql 文件
        if(!is_file($file)) exit("sql文件不存在");
        $sqls=file_get_contents($file);
        return $this->pdo->exec($sqls);
    }
	public function query($sql)
	{
	    //两种方式执行sql语句
		if (preg_match('/^(select|SHOW FULL COLUMNS FROM|SHOW TABLES FROM|SHOW CREATE TABLE)/i', $sql)) {
			return $this->pdo->query($sql);
		} else {
			return $this->pdo->exec($sql);
		}
	}
	private function formatArr($field, $isField=true) {
	    //两种输出类型，解析数组
        if (!is_array($field)) exit("好家伙，连这个参数都不给我?我可是需要array的宝宝！");
        if ($isField) {
            foreach ($field as $v) {
                @$fields .= '`'.$v.'`,';
            }
        } else {
            foreach ($field as $v) {
                @$fields .= '\''.$v.'\''.',';
            }
        }  
        $fields = rtrim($fields, ',');
        return $fields;
    }
	public function mysql_error()
	{
		$info = $this->pdo->errorInfo();
		return $info[2];
	}
	public function mysql_version()
	{
		return $this->pdo->getAttribute(constant('PDO::ATTR_SERVER_VERSION'));
	}
}