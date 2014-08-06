<?php
/**
 * EasyBphp是一个php简单的封装
 */
namespace EasyBphp {
/**
 * 存放当前访问基本请求信息
 */
global $Request;

/**
 *   HTTP和HTTPS请求url对象
 */
class HttpURL{
    const HTTPS=2;
    const HTTP=1;
    private $hash,
            $protocol,
            $host,
            $port,
            $username,
            $password,
            $path,
            $search;
    public function __construct($ProtocolOrUrl=NULL,$host=NULL,$port=NULL
                            ,$request_uri=NULL,$path=NULL,$search=NULL){
        if(is_string($ProtocolOrUrl)){
            preg_match('/(?:(http|https):\/\/)?([\w\.\-\d]+)(\/.*)?/', $url, $re_res);
            var_dump($re_res);
        }elseif(is_numeric($ProtocolOrUrl)){
            $this->protocol=$ProtocolOrUrl;
            if(!is_null($host))$this->host=$host;
            if(!is_null($port))$this->port=$port;
            if(!is_null($request_uri)){
                $tmp=explode("?", $request_uri,2);
                $this->path=$tmp[0];
                if(isset($tmp[1])){
                    $this->search=$tmp[1];
                }else{
                    $this->search='';
                }
            }
            if(is_string($path))$this->path=$path;
            if(is_string($search))$this->search=$search;
        }else{
            throw new Exception("Error Parameter", 1);
        }
    }

    static public function searchStr2Array($str){
        if(!is_string($str))throw new Exception("argument is not String Type", 1);
        $res=array();
        foreach(explode('&', $str) as $slice){
            $k_v=explode('=', $slice ,2);
            if(is_array($k_v) and count($k_v)===2){
                $key=urldecode($k_v[0]);
                $value=urldecode($k_v[1]);
                if(array_key_exists($key, $res)){
                    array_push($res[$key],urldecode($value));
                }else{
                    $res[$key]=array(urldecode($value));
                }
            }
        }
        return $res;
    }
    
    static public function searchArray2Str(array $array){
        $res='';
        foreach ($array as $row_key => $row_value) {
            $key=urlencode($row_key);
            if(is_string($row_value)){
                $value=urlencode($row_value);
                $res .= "$key=$value&";
            }elseif(is_array($row_value)){
                foreach($row_value as $e){
                    $value=urlencode($e);
                    $res .= "$key=$value&";
                }
            }
        }
        return trim($res,'&');
    }
    
    private function getAuthStr(){
        if(!empty($this->username)){
            $auth_str=$this->username;
            if(!empty($this->password)){
                $auth_str .= ":$this->password@";
            }else{
                $auth_str .= "@";
            }
        }else{
            $auth_str='';
        }
        return $auth_str;
    }
    
    /**
     *  更具相对路径计算绝对路径
     */
    private function getPathStr($path=''){
        if(empty($path)){
            return $this->path;
        }
        $relationPath=explode('/', $path);
        if(is_string($relationPath))$relationPath=array($relationPath);
        if ($relationPath[0]===''){                 //当是/开头的作为绝对路径
            $pathArray=array();
        }else{
            $pathArray=explode('/', $this->path);   //使用旧路径作为开始
            array_shift($pathArray);                //数组最后一个为文件名，之前为目录名
        }
        foreach($relationPath as $dir){             //便利相对路径，同时修改基准路径
            if ($dir === '..' and 0 < count($pathArray)){//..目录直接弹出，除非无文件可弹出
                array_pop($pathArray);
            }elseif($dir !== '.' and $dir !== '' and $dir !== '..'){    //忽略.目录和无名目录，其余路径压入基准路径
                array_push($pathArray,$dir);
            }
        }if($relationPath[count($relationPath)-1] == ''){
            array_push($pathArray,'');
        }
        array_unshift($pathArray,'');
        return join('/', $pathArray);
    }

    public function Hash($hash=NULL){
        $this->hash=$hash;
    }
    
    public function Auth($username=NULL,$password=NULL){
        $this->username=$username;
        $this->password=$password;
    }
    
    public function Search($search=NULL){
        if (is_null($search)){
            return $this->search;
        }elseif (is_string($search)){
            $this->$search=$search;
        }elseif (is_array($search)){
            self::searchArray2Str($search);
        }else{
            throw new Exception("TypeError", 1);
        }
    }
    
    public function getURL($path=''){
        $auth_str=$this->getAuthStr();
        $path_str=$this->getPathStr($path);
        $search_str=empty($this->search)?'':"?$this->search";
        $hash_str=empty($this->hash)?'':"#$this->hash";
        if( $this->protocol === self::HTTP){
            if($this-> port != 80){
                return "http://${auth_str}$this->host:$this->port${path_str}${search_str}${hash_str}";
            }else{
                return "http://${auth_str}$this->host${path_str}${search_str}${hash_str}";
            }
        }elseif($this->protocol === self::HTTPS){
            if( $this->port != 443){
                return "https://${auth_str}$this->host:$this->port${path_str}${search_str}${hash_str}";
            }else{
                return "https://${auth_str}$this->host${path_str}${search_str}${hash_str}";
            }
        }else{
            throw new Exception("unknown Protocol:$this->protocol", 1);
        }
    }

    public function __toString(){
        return $this->getURL();
    }
}
/**
 * 封装客户端请求信息
 */
class Request {
    const POST_FIRST=1;
    const GET_FIRST=2;
    public $_GET=array();
    public $_POST=array();
    /**
     * 当前请求url信息
     */
    public $_URL=NULL;

    public function __construct(){
        $this->_URL = new HttpURL(self::isHttps()?HttpURL::HTTPS:HttpURL::HTTP
                                    ,$_SERVER['SERVER_NAME'] 
                                    ,$_SERVER['SERVER_PORT']
                                    ,$_SERVER['REQUEST_URI']);
        if(self::isPost()){
            $this->_POST=HttpURL::searchStr2Array(
                file_get_contents("php://input")
            );
        }
        $this->_GET=HttpURL::searchStr2Array($this->_URL->Search());   
    }
    

    /**
     *      返回post或get中的指定key的值，如果不指定key，反或全部
     *      如果存在多个相同的key所有值存入一个数组
     *      @param name string
     *      @param default any
     *      @param merge_type self::POST_FIRST || self::GET_FIRST
     *      @return array 
     */
    public function Parameter(   $name = NULL, 
                                 $default = array() ,
                                 $merge_type = self::POST_FIRST
                             )
    {
        if($merge_type===self::POST_FIRST){
            $first=$this->_POST;$second=$this->_GET;
        }elseif($merge_type===self::GET_FIRST){
            $first=$this->_GET;$second=$this->_POST;
        }else{
            throw new Exception("unknow Merge Type", 1);
        }
        if(is_null($name)){
            foreach($second as $key => $value){
                if(array_key_exists($key, $first)){
                    $first[$key]=array_merge($first[$key],$second[$key]);
                }else{
                    $first[$key]=$second[$key];
                }
            }
            return $first;
        }else{
            if(array_key_exists($name, $first) and array_key_exists($name,$second)){
                return array_merge((array)$first[$name],(array)$second[$name]);
            }elseif(array_key_exists($name, $first)){
                return (array)$first[$name];
            }elseif(array_key_exists($name,$second)){
                return (array)$second[$name];
            }else{
                return $default;
            }
        }
        $get=$this->get($name);
        $post=$this->post($name);
        if(is_null($get) and ! is_null($post))return $post;
        if(!is_null($get) and is_null($post))return $post;
        if(is_null($get) and is_null($post))return $default;
        else{
            if($merge_type===self::POST_FIRST)
                return array_merge((array)$post,(array)$get);
            elseif($merge_type===self::GET_FIRST)
                return array_merge((array)$get,(array)$post);
            else{
                throw new Exception("unknow Merge Type");
            }
        }
    }
    /**
     *    获取Get数组
     *    如果存在多个相同的key所有值存入一个数组
     *    当指定name是返回指定的name的值
     *    @param name string,NULL
     *    @param default any
     *    @return array 
     */
    public function Get($name = NULL , $default = array()) {
        if(is_null($name)){
            return $this->_GET;
        }
        if (array_key_exists($name, $this->_GET)) {
            return $this->_GET[$name];
        }else{
            return $default;
        }
        
    }
    /**
     *    获取Post数组
     *    如果存在多个相同的key所有值存入一个数组
     *    @param name string,NULL
     *    @param default any
     *    @return array 
     */
    public function Post($name = NULL, $default = array()) {
        if(is_null($name)){
            return $this->_POST;
        }
        if (array_key_exists($name, $this->_POST)) {
            return $this->_POST[$name];
        }else{
            return $default;
        }
    }

    /**
     * 获取cookie信息
     * @param name string
     * @param default any
     * @return string or default
     */
    static public function Cookie($name, $default = NULL) {
        if (array_key_exists($name, $_COOKIE)){
            return $_COOKIE[$name];
        }else{
            return $default;
        }
    }
    
    /**
     * 返回当前请求的Referer信息
     * 当没有referer是返回默认值
     * @param $default string or NULL
     * return url
     */
    static public function Referer($default = NULL) {
        if(empty($_SERVER['HTTP_REFERER'])){
            return $default;
        }else{
            return $_SERVER['HTTP_REFERER'];
        }
    }

    /**
     * 获取访问着ip地址
     * @param proxy boolean 是否不跟踪代理信息
     * @return IP string
     */
    static public function IP($proxy = FALSE) {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) and !$proxy ) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }
    
    /**
     *
     * @return UserAgent string
     */
    static public function UserAgent() {
        return $_SERVER['HTTP_USER_AGENT'];
    }
    
    /**
     * 是否为https连入 
     * @return boolean
     */
    static public function isHttps(){
        return (!empty($_SERVER['HTTPS']) 
                && $_SERVER['HTTPS'] != 'off') ? TRUE : FALSE;
    }
    
    /**
     * 是否为post请求
     * @return boolean
     */
    static public function isPost(){
        return (array_key_exists('REQUEST_METHOD', $_SERVER) 
                && $_SERVER['REQUEST_METHOD'] === 'POST' )?TRUE:FALSE;
    }
    /**
     * 返回数组的第一个元素，如果没有元素返回默认值
     * @param array array
     * @param default
     * @return any 如果存在返回第一个元素，否则返回默认值
     */
    static public function First($array,$default=NULL){
        $keys=array_keys($array);
        return $array?$array[$keys[0]]:$default;
    }
}
/**
 * 
 */
class Response {
    static public function ContentType($class,$type) {
        if(is_string($class) && is_string($type)){
            header("Content-type: $class/$type");
        }else{
            throw new Exception("Error Processing Request", 1);
        }
        
    }
    static public function Location($url,$code=302,$default='/'){
        if(is_string($url)){
            if($url=== $_SERVER['REQUEST_URI'] ) $url=$default;
            Header("Location: $url"); 
            self::Code($code);
        }
    }
    static public function Code($code = NULL){
        if (function_exists('http_response_code')){
            return http_response_code($code);
        }elseif(!is_null($code)){
            switch ($code) {
                case 100: $text = 'Continue'; break;
                case 101: $text = 'Switching Protocols'; break;
                case 200: $text = 'OK'; break;
                case 201: $text = 'Created'; break;
                case 202: $text = 'Accepted'; break;
                case 203: $text = 'Non-Authoritative Information'; break;
                case 204: $text = 'No Content'; break;
                case 205: $text = 'Reset Content'; break;
                case 206: $text = 'Partial Content'; break;
                case 300: $text = 'Multiple Choices'; break;
                case 301: $text = 'Moved Permanently'; break;
                case 302: $text = 'Moved Temporarily'; break;
                case 303: $text = 'See Other'; break;
                case 304: $text = 'Not Modified'; break;
                case 305: $text = 'Use Proxy'; break;
                case 400: $text = 'Bad Request'; break;
                case 401: $text = 'Unauthorized'; break;
                case 402: $text = 'Payment Required'; break;
                case 403: $text = 'Forbidden'; break;
                case 404: $text = 'Not Found'; break;
                case 405: $text = 'Method Not Allowed'; break;
                case 406: $text = 'Not Acceptable'; break;
                case 407: $text = 'Proxy Authentication Required'; break;
                case 408: $text = 'Request Time-out'; break;
                case 409: $text = 'Conflict'; break;
                case 410: $text = 'Gone'; break;
                case 411: $text = 'Length Required'; break;
                case 412: $text = 'Precondition Failed'; break;
                case 413: $text = 'Request Entity Too Large'; break;
                case 414: $text = 'Request-URI Too Large'; break;
                case 415: $text = 'Unsupported Media Type'; break;
                case 500: $text = 'Internal Server Error'; break;
                case 501: $text = 'Not Implemented'; break;
                case 502: $text = 'Bad Gateway'; break;
                case 503: $text = 'Service Unavailable'; break;
                case 504: $text = 'Gateway Time-out'; break;
                case 505: $text = 'HTTP Version not supported'; break;
                default:
                    throw new Exception("Unknown http status code:$code",1);
                break;
            }
            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
            header($protocol . ' ' . $code . ' ' . $text);
            $GLOBALS['http_response_code'] = $code;
            return $code;
        }else{
             return (isset($GLOBALS['http_response_code']) ? $GLOBALS['http_response_code'] : 200);
        }
    }
}

}

namespace   {
if (!function_exists('json_last_error_msg')) {
    function json_last_error_msg() {
        static $errors = array(
            JSON_ERROR_NONE             => null,
            JSON_ERROR_DEPTH            => 'Maximum stack depth exceeded',
            JSON_ERROR_STATE_MISMATCH   => 'Underflow or the modes mismatch',
            JSON_ERROR_CTRL_CHAR        => 'Unexpected control character found',
            JSON_ERROR_SYNTAX           => 'Syntax error, malformed JSON',
            JSON_ERROR_UTF8             => 'Malformed UTF-8 characters, possibly incorrectly encoded'
        );
        $error = json_last_error();
        return array_key_exists($error, $errors) ? $errors[$error] : "Unknown error ({$error})";
    }
}
}




?>