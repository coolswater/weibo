<?php
namespace Hexd;
/**
 * 新浪微博 OAuth 认证类(OAuth2)
 * 授权机制说明请大家参考微博开放平台文档：{@link http://open.weibo.com/wiki/Oauth2}
 *
 * @author: Hexd <coolswater@163.com>
 * @Date: 2019/04/01
 * @version 1.0
 */
class Weibo {
    public $client_id;
    public $client_secret;
    public $access_token;
    public $refresh_token;
    public $redirect_uri;
    public $timeout = 30;
    public $debug = FALSE;
    public $format = 'json';
    public $decode_json = TRUE;
    public $connecttimeout = 30;
	public static $boundary='';
    public $ssl_verifypeer = FALSE;
    public static $host = "https://api.weibo.com/2/";
    public static $authorizeURL = 'https://api.weibo.com/oauth2/authorize';
    public static $accessTokenURL = 'https://api.weibo.com/oauth2/access_token';
    public static $getUserInfoURL = 'https://api.weibo.com/2/users/show.json';
    public static $weiboShare = 'https://api.weibo.com/2/statuses/share.json';
    //构造方法
    function __construct($options) {
        $this->client_id = $options['client_id'];
        $this->redirect_uri = $options['redirect_uri'];
        $this->client_secret = $options['client_secret'];
    }
    
    /**
     * authorize接口(获取code)
     *
     * 对应API：{@link http://open.weibo.com/wiki/Oauth2/authorize Oauth2/authorize}
     *
     * @param string $redirect_uri      授权后的回调地址,站外应用需与回调地址一致,站内应用需要填写canvas page的地址
     * @param string $response_type     支持的值包括 code 和token 默认值为code
     * @param string $state             用于保持请求和回调的状态。在回调时,会在Query Parameter中回传该参数
     * @param string $display           授权页面类型 可选范围:
     *          - default		        默认授权页面
     *          - mobile		        支持html5的手机
     *          - popup			        弹窗授权页
     *          - wap1.2		        wap1.2页面
     *          - wap2.0		        wap2.0页面
     *          - js			        js-sdk 专用 授权页面是弹窗，返回结果为js-sdk回掉函数
     *          - apponweibo	        站内应用专用,站内应用不传display参数,并且response_type为token时,默认使用改display.授权后不会返回access_token，只是输出js刷新站内应用父框架
     *
     * @return array
     */
    function getAuthorizeUrl( $response_type = 'code', $state = NULL, $display = NULL ) {
        $params = array();
        $params['client_id'] = $this->client_id;
        $params['redirect_uri'] = $this->redirect_uri;
        $params['response_type'] = $response_type;
        $params['state'] = $state;
        $params['display'] = $display;
        
        return self::$authorizeURL . http_build_query($params);
    }
    
    /**
     * access_token接口
     * 对应API：{@link http://open.weibo.com/wiki/OAuth2/access_token OAuth2/access_token}
     * @param string $code
     * @param string $redirect_uri 回调地址
     *
     * @return array
     */
    function getAccessToken($code) {
        $params = array();
        $params['code'] = $code;
        $params['client_id'] = $this->client_id;
        $params['redirect_uri'] = $this->redirect_uri;
        $params['client_secret'] = $this->client_secret;
        $params['grant_type'] = 'authorization_code';
        
        $token = $this->post(self::$accessTokenURL, $params);
		
        if ( is_array($token) && !isset($token['error']) ) {
            $this->access_token = $token['access_token'];
        } else {
            throw new \Exception("get access token failed." . $token['error']);
        }
        
        return $token;
    }
    
    //获取用户信息
    public function getUserInfo($access_token,$uid){
        $param = array(
            'uid'          => $uid,
            'access_token' => $access_token,
        );
        $userInfo = $this->get(self::$getUserInfoURL,$param);
        
        return $userInfo;
    }

    /**发送微博
     * @param   string $accessToken access_token
     * @param   string $status      分享到微博的文本内容
     * @param   string $pic         分享到微博的图片
     * @param   string $realIp      开发者上报的操作用户真实IP
     *
     */
    public function publishWeibo($accessToken,$status,$pic,$realIp){
        $param = array(
            'access_token'  => $accessToken,
            'status'        => $status,
            'pic'           => $pic,
            'rip'           => $realIp,
        );
        $result = $this->post(self::$weiboShare,$param);
        var_dump($result);
    }
    
    /**
     * 发起get请求
     *
     * @return mixed
     */
    function get($url, $parameters = array()) {
        $response = $this->oAuthRequest($url, 'GET', $parameters);
        if ($this->format === 'json' && $this->decode_json) {
            return json_decode($response, true);
        }
        return $response;
    }
    
    /**
     *发起POST请求
     *
     * @return mixed
     */
    function post($url, $parameters = array(), $multi = false) {
        $response = $this->oAuthRequest($url, 'POST', $parameters, $multi );
        if ($this->format === 'json' && $this->decode_json) {
            return json_decode($response, true);
        }
        return $response;
    }
    
    /**
     * 请求格式化
     *
     * @return string
     * @ignore
     */
    function oAuthRequest($url, $method, $parameters, $multi = false) {
        
        if (strrpos($url, 'http://') !== 0 && strrpos($url, 'https://') !== 0) {
            $url = "{$this->host}{$url}.{$this->format}";
        }
        
        switch ($method) {
            case 'GET':
                $url = $url . '?' . http_build_query($parameters);
                return $this->http($url, 'GET');
            default:
                $headers = array();
                if (!$multi && (is_array($parameters) || is_object($parameters)) ) {
                    $body = http_build_query($parameters);
                } else {
                    $body = self::build_http_query_multi($parameters);
                    $headers[] = "Content-Type: multipart/form-data; boundary=" . self::$boundary;
                }
                return $this->http($url, $method, $body, $headers);
        }
    }
    
    /**
     * 发起HTTP request
     *
     * @return string API results
     * @ignore
     */
    function http($url, $method, $postfields = NULL, $headers = array()) {
        $this->http_info = array();
        $ci = curl_init();
        /* Curl settings */
        curl_setopt($ci, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, $this->connecttimeout);
        curl_setopt($ci, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ci, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ci, CURLOPT_ENCODING, "");
        curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, $this->ssl_verifypeer);
        if (version_compare(phpversion(), '5.4.0', '<')) {
            curl_setopt($ci, CURLOPT_SSL_VERIFYHOST, 1);
        } else {
            curl_setopt($ci, CURLOPT_SSL_VERIFYHOST, 2);
        }
        curl_setopt($ci, CURLOPT_HEADERFUNCTION, array($this, 'getHeader'));
        curl_setopt($ci, CURLOPT_HEADER, FALSE);
        
        switch ($method) {
            case 'POST':
                curl_setopt($ci, CURLOPT_POST, TRUE);
                if (!empty($postfields)) {
                    curl_setopt($ci, CURLOPT_POSTFIELDS, $postfields);
                    $this->postdata = $postfields;
                }
                break;
            case 'DELETE':
                curl_setopt($ci, CURLOPT_CUSTOMREQUEST, 'DELETE');
                if (!empty($postfields)) {
                    $url = "{$url}?{$postfields}";
                }
        }
        
        if ( isset($this->access_token) && $this->access_token )
            $headers[] = "Authorization: OAuth2 ".$this->access_token;
        
        if ( !empty($this->remote_ip) ) {
            if ( defined('SAE_ACCESSKEY') ) {
                $headers[] = "SaeRemoteIP: " . $this->remote_ip;
            } else {
                $headers[] = "API-RemoteIP: " . $this->remote_ip;
            }
        } else {
            if ( !defined('SAE_ACCESSKEY') ) {
                $headers[] = "API-RemoteIP: " . $_SERVER['REMOTE_ADDR'];
            }
        }
        curl_setopt($ci, CURLOPT_URL, $url );
        curl_setopt($ci, CURLOPT_HTTPHEADER, $headers );
        curl_setopt($ci, CURLINFO_HEADER_OUT, TRUE );
        
        $response = curl_exec($ci);
        $this->http_code = curl_getinfo($ci, CURLINFO_HTTP_CODE);
        $this->http_info = array_merge($this->http_info, curl_getinfo($ci));
        $this->url = $url;
        
        if ($this->debug) {
            echo "=====post data======\r\n";
            var_dump($postfields);
            
            echo "=====headers======\r\n";
            print_r($headers);
            
            echo '=====request info====='."\r\n";
            print_r( curl_getinfo($ci) );
            
            echo '=====response====='."\r\n";
            print_r( $response );
        }
        curl_close ($ci);
        return $response;
    }
    
    /**
     * 获取请求头
     *
     * @return int
     * @ignore
     */
    function getHeader($ch, $header) {
        $i = strpos($header, ':');
        if (!empty($i)) {
            $key = str_replace('-', '_', strtolower(substr($header, 0, $i)));
            $value = trim(substr($header, $i + 2));
            $this->http_header[$key] = $value;
        }
        return strlen($header);
    }

    /**
     * @ignore
     */
    public static function build_http_query_multi($params) {
        if (!$params) return '';

        uksort($params, 'strcmp');

        $pairs = array();

        self::$boundary = $boundary = uniqid('------------------');
        $MPboundary = '--'.$boundary;
        $endMPboundary = $MPboundary. '--';
        $multipartbody = '';

        foreach ($params as $parameter => $value) {

            if( in_array($parameter, array('pic', 'image')) && $value{0} == '@' ) {
                $url = ltrim( $value, '@' );
                $content = file_get_contents( $url );
                $array = explode( '?', basename( $url ) );
                $filename = $array[0];

                $multipartbody .= $MPboundary . "\r\n";
                $multipartbody .= 'Content-Disposition: form-data; name="' . $parameter . '"; filename="' . $filename . '"'. "\r\n";
                $multipartbody .= "Content-Type: image/unknown\r\n\r\n";
                $multipartbody .= $content. "\r\n";
            } else {
                $multipartbody .= $MPboundary . "\r\n";
                $multipartbody .= 'content-disposition: form-data; name="' . $parameter . "\"\r\n\r\n";
                $multipartbody .= $value."\r\n";
            }

        }

        $multipartbody .= $endMPboundary;
        return $multipartbody;
    }
}
