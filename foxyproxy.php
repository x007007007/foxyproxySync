<?php
include_once 'EasyB.php';
$Request=new EasyBphp\Request;

define('FOXYPROXY_RULE_TYPE_REGEXP', 1);
define('FOXYPROXY_RULE_TYPE_PATTERN',2);
define('FOXYPROXY_RULE_TYPE_DOMAIN', 3);

$db=new PDO('sqlite:/tmp/foxyproxy.db');
if($db->query("select count(*) from sqlite_master")->fetchColumn() === '0'){
    $db_create=<<<eof
    Create Table foxyproxy_rule (
        id INTEGER PRIMARY KEY AUTOINCREMENT,   
        class_id integer,
        type integer ,
        name varchar(255) ,
        name_class integer,
        is_enabled integer DEFAULT 0 ,
        is_multiline integer DEFAULT 0 ,
        is_blacklist integer DEFAULT 0 ,
        is_casesensitive integer DEFAULT 0 ,
        domain varchar(255) ,
        pattern text
    );
    Create Table foxyproxy_config(
        id INTEGER PRIMARY KEY AUTOINCREMENT ,
        name varchar(255)
    );
    Create Table foxypoxy_rule_name(
        id INTEGER PRIMARY KEY AUTOINCREMENT ,
        name varchar(255)
    );
eof;
    if ($db->exec($db_create)){
        echo '创建数据库成功';
    }else{
        echo '创建数据库失败';
    }
}

/**
 * 将模式文件导入到数据库
 * 如果有重名则用新的数据替换，如果不存在则创建
 * @todo 拆分正则，自动产生匹配正则
 */
function updateByFoxyProxyFile($path,PDO $db){
    $json=json_decode(file_get_contents($path),TRUE);
    if(JSON_ERROR_NONE !==json_last_error())throw new Exception(json_last_error_msg(), 1);
    if(!array_key_exists('patterns', $json)){
        throw new Exception("Not a Usefully FoxyProxy", 1);
    }else{
        $patterns=$json['patterns'];
        $insert_sql=<<<EOF
            insert into foxyproxy_rule (
                class_id,type,name,name_class,is_enabled,is_multiline,
                is_blacklist,is_casesensitive,pattern,domain
            ) values(
                :class_id,:type,:name,:name_class,:is_enabled,:is_multiline,
                :is_blacklist,:is_casesensitive,:pattern,:domain
            );
EOF;
        $update_by_name_sql=<<<EOF
            UPDATE foxyproxy_rule SET class_id=:class_id ,type=:type,
            name_class=:name_class,is_enabled=:is_enabled,
            is_multiline=:is_multiline,is_blacklist=:is_blacklist,
            is_casesensitive=:is_casesensitive,pattern=:pattern,domain=:domain
            WHERE name=:name
EOF;
        $find_name_sql='select count(*) from foxyproxy_rule where name = ?';
        $prepare_config=array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY);
        $prepare_find_name_sdh=$db->prepare($find_name_sql ,$prepare_config);
        $prepare_insert_sdh=$db->prepare($insert_sql, $prepare_config);
        $prepare_update_by_name_sdh=$db->prepare($update_by_name_sql);
        foreach ($patterns as $value) {
            $name=$value['name'];
            $pattern=$value['pattern'];
            $enabled=$value['enabled'];
            $isRegEx=$value['isRegEx'];
            $caseSensitive=$value['caseSensitive'];
            $blackList=$value['blackList'];
            $multiLine=$value['multiLine'];
            // if($isRegEx){
                // if(substr($pattern,0,23)=== "^https?://(?:.*?\.)?(?:"
                   // and substr($pattern,-4)=== ').*$' ){
                    // $domains=explode('|', substr($pattern,23,strlen($pattern)-27));
                    // var_dump($domains);
                // }
            // }
            $prepare_find_name_sdh->execute(array($name));
            $count=$prepare_find_name_sdh->fetchColumn();
            if($count[0]==='0'){    //更具名称判断是否添加项目 
                if(!$prepare_insert_sdh->execute(array(
                    ':class_id'      => 0,
                    ':type' => ($isRegEx?FOXYPROXY_RULE_TYPE_REGEXP:FOXYPROXY_RULE_TYPE_PATTERN),
                    ':name' => $name,
                    ':name_class'   => 0,
                    ':is_enabled'   =>($enabled?1:0),
                    ':is_multiline' =>($multiLine?1:0),
                    ':is_blacklist' =>($blackList?1:0),
                    ':is_casesensitive'=>($blackList?1:0),
                    ':pattern'      =>$pattern,
                    ':domain'       =>'',
                ))){
                    print_r($db->errorInfo());
                }
            }else{
                if(!$prepare_update_by_name_sdh->execute(array(
                    ':class_id'      => 0,
                    ':type' => ($isRegEx?FOXYPROXY_RULE_TYPE_REGEXP:FOXYPROXY_RULE_TYPE_PATTERN),
                    ':name' => $name,
                    ':name_class'   => 0,
                    ':is_enabled'   =>($enabled?1:0),
                    ':is_multiline' =>($multiLine?1:0),
                    ':is_blacklist' =>($blackList?1:0),
                    ':is_casesensitive'=>($blackList?1:0),
                    ':pattern'      =>$pattern,
                    ':domain'       =>'',
                ))){
                    print_r($db->errorInfo());
                }
            }
        }
    }

}

/**
 * 从数据库中生成FoxyProxy的导出文件
 */
function generateFoxyProxyJsonStr(PDO $db){
    $res_array=array();
    $select_rule_sql=<<<EOF
    select class_id,type,name,name_class,is_enabled,is_multiline,
    is_blacklist,is_casesensitive,pattern,domain from foxyproxy_rule;
EOF;
    $q=$db->query($select_rule_sql);
    foreach($q as $v){
        $res_row=array();
        $res_row['caseSensitive']=($v['is_casesensitive']==1?TRUE:FALSE);
        $res_row['enabled']=(bool)$v['is_enabled'];
        $res_row['name']=$v['name'];
        $res_row['pattern']=$v['pattern'];
        $res_row['isRegEx']=($v['type']%2==1?TRUE:FALSE);
        $res_row['blackList']=(bool)$v['is_blacklist'];
        $res_row['multiLine']=(bool)$v['is_multiline'];
        array_push($res_array,$res_row);
    }
    return json_encode(array('patterns' => $res_array));   
}

/**
 * 列出所有数据
 */
function MakeFoxyProxyList(PDO $db){
    $select_rule_sql=<<<EOF
    select id,class_id,type,name,name_class,is_enabled,is_multiline,
    is_blacklist,is_casesensitive,pattern,domain from foxyproxy_rule;
EOF;
    $q=$db->query($select_rule_sql);
    ?><table><?php 
    foreach ($q as $key => $value) {
    ?>
        <tr><td><?php echo $value['name'];?></td>
            <td><?php echo $value['pattern'];?></td>
            <td><a href="?type=delete&id=<?php echo $value['id']?>">删除</a></td>
        </tr>
    <?php }?></table><?php
}

function deleteRow($ids,PDO $db){
    $delete_sth=$db->prepare('delete from foxyproxy_rule where id = ?');
    foreach ($ids as $id) {
        $delete_sth->execute(array((int)$id));
    }
}

$para_type=$Request::First($Request->Parameter('type'));
switch($para_type){
    case 'upload':
        if(array_key_exists('foxyproxyfile', $_FILES)){
            $upload_msg='配置文件上传成功';
            $filepath=$_FILES['foxyproxyfile']['tmp_name'];
             try{
                $msg=updateByFoxyProxyFile($filepath,$db);
                $upload_msg="配置文件导入成功<pre>$msg</pre>";
                unlink($filepath);
            }catch (Exception $e) {
                $upload_msg=$e->getMessage();
            }
        }else{
            $upload_msg='选择上传配置文件';
        }
        ?>
        <html>
            <head>
                <meta http-equiv="content-type" content="text/html; charset=utf-8" />
                <title>upload规则文件</title>
            </head>
            <body>
                <h2><?php echo $upload_msg ?></h2>
                <form action="" method="post" enctype="multipart/form-data">
                    <input type="file" name='foxyproxyfile'/>
                    <input type="submit" name='submit' value="upload" 
                    accept="application/json,text/plain" />
                </form>
            </body>
        </html>
        <?php
    break;
    case 'list':
        ?>
        <html>
            <head>
                <meta />
                <title></title>
            </head>
            <body>
                <?php MakeFoxyProxyList($db); ?>
            </body>
        </html>
        <?php
        break;
    case 'delete':
        deleteRow($Request->Parameter('id'),$db);
        EasyBphp\Response::Location(EasyBphp\Request::Referer());
        break;
    default:
        EasyBphp\Response::ContentType('application','json');
        echo generateFoxyProxyJsonStr($db);
        exit();
        break;
}



?>